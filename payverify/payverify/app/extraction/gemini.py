"""Google Gemini API client with multi-key rotation, token-bucket rate limiting,
exponential backoff, request telemetry, and resilient free-tier handling.
"""
import collections
import logging
import random
import threading
import time
from typing import Any, Dict, List, Optional, Tuple

import httpx

from ..config import settings
from .engines import ExtractionError

log = logging.getLogger("payverify.gemini")


class GeminiQuotaExhaustedError(ExtractionError):
    """Raised when all configured Gemini API keys have exhausted their free-tier quota."""
    pass


class GeminiAPIError(ExtractionError):
    """Raised when the Gemini API returns a permanent client or model error."""
    pass


class KeyRotator:
    """Thread-safe multi-key rotator with cooldown management for 429 rate limits."""

    def __init__(self, keys: Optional[List[str]] = None, cooldown_seconds: float = 60.0):
        self._keys: List[str] = [k.strip() for k in (keys or []) if k.strip()]
        self._cooldown_seconds = cooldown_seconds
        self._cooldowns: Dict[str, float] = {}  # key -> timestamp when cooldown expires
        self._index = 0
        self._lock = threading.Lock()

    def set_keys(self, keys: List[str]) -> None:
        with self._lock:
            self._keys = [k.strip() for k in keys if k.strip()]
            self._cooldowns.clear()
            self._index = 0

    def get_key(self) -> Tuple[str, int]:
        """Return the next available key and total active key count."""
        with self._lock:
            if not self._keys:
                raise GeminiAPIError("No Gemini API keys configured. Set PV_GEMINI_API_KEY or PV_GEMINI_API_KEYS.")

            now = time.monotonic()
            # Clean expired cooldowns
            self._cooldowns = {k: exp for k, exp in self._cooldowns.items() if exp > now}

            # Find next healthy key starting from current index
            n = len(self._keys)
            for i in range(n):
                idx = (self._index + i) % n
                k = self._keys[idx]
                if k not in self._cooldowns:
                    self._index = (idx + 1) % n
                    return k, n

            # All keys are in cooldown; find the one that expires soonest
            soonest_key = min(self._cooldowns.keys(), key=lambda k: self._cooldowns[k])
            wait_time = self._cooldowns[soonest_key] - now
            if wait_time <= 5.0:
                # Wait briefly if cooldown is almost over
                time.sleep(max(0.1, wait_time))
                del self._cooldowns[soonest_key]
                return soonest_key, n

            raise GeminiQuotaExhaustedError(
                f"All {n} Gemini API key(s) are rate-limited. Next key resets in {wait_time:.1f}s."
            )

    def mark_cooldown(self, key: str, custom_cooldown: Optional[float] = None) -> None:
        """Mark a key as rate-limited/in cooldown."""
        with self._lock:
            duration = custom_cooldown or self._cooldown_seconds
            self._cooldowns[key] = time.monotonic() + duration
            log.warning("Gemini key [..%s] placed on cooldown for %.1fs (HTTP 429)", key[-4:], duration)

    def get_status(self) -> Dict[str, int]:
        with self._lock:
            now = time.monotonic()
            active_cooldowns = sum(1 for exp in self._cooldowns.values() if exp > now)
            total = len(self._keys)
            return {
                "total_keys": total,
                "healthy_keys": max(0, total - active_cooldowns),
                "in_cooldown": active_cooldowns,
            }


class RateLimiter:
    """Token-bucket rate limiter enforcing requests-per-minute (RPM) constraints."""

    def __init__(self, rpm: int = 14):
        self.rpm = max(1, rpm)
        self.capacity = float(self.rpm)
        self.tokens = self.capacity
        self.last_update = time.monotonic()
        self._lock = threading.Lock()

    def acquire(self, timeout: float = 10.0) -> bool:
        """Acquire a token, waiting if necessary up to timeout seconds."""
        deadline = time.monotonic() + timeout
        while True:
            with self._lock:
                now = time.monotonic()
                elapsed = now - self.last_update
                self.last_update = now
                self.tokens = min(self.capacity, self.tokens + elapsed * (self.capacity / 60.0))

                if self.tokens >= 1.0:
                    self.tokens -= 1.0
                    return True

                missing = 1.0 - self.tokens
                wait_sec = missing / (self.capacity / 60.0)

            if time.monotonic() + wait_sec > deadline:
                return False
            time.sleep(min(wait_sec, 0.5))


class GeminiTelemetry:
    """Thread-safe telemetry collector for API status, queue metrics, and response times."""

    def __init__(self, max_history: int = 50):
        self._lock = threading.Lock()
        self.latencies: collections.deque = collections.deque(maxlen=max_history)
        self.requests_total = 0
        self.requests_success = 0
        self.requests_failed = 0
        self.quota_exhausted_count = 0
        self.last_error: Optional[str] = None
        self.active_in_flight = 0

    def record_start(self) -> None:
        with self._lock:
            self.active_in_flight += 1

    def record_success(self, duration_sec: float) -> None:
        with self._lock:
            self.active_in_flight = max(0, self.active_in_flight - 1)
            self.requests_total += 1
            self.requests_success += 1
            self.latencies.append(duration_sec * 1000.0)

    def record_failure(self, error: Exception, is_quota: bool = False) -> None:
        with self._lock:
            self.active_in_flight = max(0, self.active_in_flight - 1)
            self.requests_total += 1
            self.requests_failed += 1
            if is_quota:
                self.quota_exhausted_count += 1
            self.last_error = str(error)[:160]

    def get_summary(self, key_status: Dict[str, int]) -> Dict[str, Any]:
        with self._lock:
            avg_lat = round(sum(self.latencies) / len(self.latencies), 1) if self.latencies else 0.0
            if key_status["total_keys"] == 0:
                health = "unconfigured"
            elif key_status["healthy_keys"] == 0:
                health = "exhausted"
            elif self.requests_failed > 0 and (self.requests_failed / max(1, self.requests_total)) > 0.4:
                health = "degraded"
            else:
                health = "healthy"

            return {
                "health": health,
                "avg_verification_time_ms": avg_lat,
                "in_flight_requests": self.active_in_flight,
                "requests_total": self.requests_total,
                "requests_success": self.requests_success,
                "requests_failed": self.requests_failed,
                "quota_exhausted_count": self.quota_exhausted_count,
                "last_error": self.last_error,
                "keys": key_status,
            }


# Global client components
_keys = [k.strip() for k in (settings.gemini_api_keys.split(",") if settings.gemini_api_keys else [settings.gemini_api_key]) if k.strip()]
key_rotator = KeyRotator(_keys)
rate_limiter = RateLimiter(settings.gemini_rate_limit_rpm)
telemetry = GeminiTelemetry()


def update_key_pool() -> None:
    """Reload keys if settings changed at runtime."""
    raw = settings.gemini_api_keys or settings.gemini_api_key
    keys = [k.strip() for k in raw.split(",") if k.strip()]
    key_rotator.set_keys(keys)


class GeminiVisionClient:
    """High-reliability client for Google Gemini Multimodal Vision API."""

    def __init__(self, model: Optional[str] = None):
        self.model = model or settings.gemini_model or "gemini-2.5-flash"

    def generate(self, parts: List[Dict[str, Any]], system_instruction: str, timeout: Optional[float] = None) -> str:
        """Call Gemini generateContent with automatic key rotation, rate limiting, and exponential backoff."""
        update_key_pool()
        req_timeout = timeout or settings.ai_timeout_seconds

        # Enforce local RPM throttle
        if not rate_limiter.acquire(timeout=req_timeout):
            raise GeminiQuotaExhaustedError("Rate limiter timeout: too many requests per minute on free tier")

        telemetry.record_start()
        start_time = time.monotonic()
        max_attempts = 4

        for attempt in range(max_attempts):
            try:
                key, total_keys = key_rotator.get_key()
            except GeminiQuotaExhaustedError as e:
                telemetry.record_failure(e, is_quota=True)
                raise

            headers = {
                "x-goog-api-key": key,
                "Content-Type": "application/json",
            }
            url = f"https://generativelanguage.googleapis.com/v1beta/models/{self.model}:generateContent"

            payload = {
                "system_instruction": {"parts": [{"text": system_instruction}]},
                "contents": [{"role": "user", "parts": parts}],
                "generationConfig": {
                    "temperature": 0.0,
                    "response_mime_type": "application/json",
                },
            }

            try:
                resp = httpx.post(url, headers=headers, json=payload, timeout=req_timeout)

                if resp.status_code == 200:
                    data = resp.json()
                    candidates = data.get("candidates") or []
                    if not candidates:
                        raise GeminiAPIError("Gemini returned no candidates (possible content filter)")
                    text_parts = candidates[0].get("content", {}).get("parts", [])
                    result_text = "".join(p.get("text", "") for p in text_parts)
                    duration = time.monotonic() - start_time
                    telemetry.record_success(duration)
                    return result_text

                # Handle 429 Quota / Rate Limit
                if resp.status_code == 429:
                    key_rotator.mark_cooldown(key, custom_cooldown=60.0)
                    if attempt < max_attempts - 1:
                        backoff = (2 ** attempt) + random.uniform(0.1, 0.8)
                        log.info("Gemini 429 encountered; rotating key and retrying in %.2fs", backoff)
                        time.sleep(backoff)
                        continue
                    raise GeminiQuotaExhaustedError("Gemini free-tier quota exhausted across available keys")

                # Handle 503 / 500 Transient Google Server Overload
                if resp.status_code in (500, 502, 503, 504):
                    if attempt < max_attempts - 1:
                        backoff = (1.5 ** attempt) + random.uniform(0.1, 0.5)
                        log.info("Gemini HTTP %s server error; retrying in %.2fs", resp.status_code, backoff)
                        time.sleep(backoff)
                        continue
                    raise GeminiAPIError(f"Gemini server error {resp.status_code}: {resp.text[:120]}")

                # Non-retryable client error (e.g. 400 Bad Request, 403 Forbidden)
                err_msg = f"Gemini API error {resp.status_code}: {resp.text[:150]}"
                exc = GeminiAPIError(err_msg)
                telemetry.record_failure(exc)
                raise exc

            except httpx.RequestError as e:
                if attempt < max_attempts - 1:
                    time.sleep(1.0)
                    continue
                exc = GeminiAPIError(f"Gemini network connection failed: {e}")
                telemetry.record_failure(exc)
                raise exc from e
            except Exception as e:
                telemetry.record_failure(e, is_quota=isinstance(e, GeminiQuotaExhaustedError))
                raise

        exc = GeminiQuotaExhaustedError("Failed to obtain Gemini response after retries")
        telemetry.record_failure(exc, is_quota=True)
        raise exc
