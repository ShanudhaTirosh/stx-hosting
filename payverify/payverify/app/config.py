from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="PV_", env_file=".env", extra="ignore")

    env: str = "dev"                      # set PV_ENV=production in production (enables startup safety checks)
    data_key: str = ""                    # Fernet key: encrypts proof files and MFA secrets at rest
    require_staff_mfa: bool = True
    login_max_failures: int = 5
    login_window_minutes: int = 15
    database_url: str = "sqlite:///./payverify.db"
    jwt_secret: str = "change-me-in-production"
    jwt_ttl_minutes: int = 30
    storage_dir: str = "./storage"
    max_upload_bytes: int = 8 * 1024 * 1024
    # Expected last digits of YOUR ComBank account; imports that declare a different tail are refused.
    bank_account_tail: str = ""
    claims_per_order_per_hour: int = 5
    # Match window (days) around claim time for candidate bank credits
    match_window_before_days: int = 3
    match_window_after_days: int = 3
    # Shown to customers on the pay page (only what they need to pay you)
    pay_to_bank: str = "Commercial Bank of Ceylon PLC"
    pay_to_account_name: str = ""
    pay_to_account_number: str = ""
    cors_origins: str = "http://localhost:3000"
    # Optional JSON to adapt the statement importer to your bank's export without code changes, e.g.
    # {"columns": {"date": ["Txn Date"], "credit": ["Cr Amount"], "ref": ["Cheque/Ref"]}, "date_formats": ["%d.%m.%Y"]}
    statement_profile: str = ""
    # Data retention (PDPA). PLACEHOLDER defaults: confirm with your accountant/lawyer what you must keep, and for how long.
    retention_verified_days: int = 730
    retention_rejected_days: int = 90
    retention_sms_body_days: int = 90
    retention_login_attempt_days: int = 30
    # Phase 4: Android SMS client
    sms_sender_allowlist: str = "COMBANK,ComBank,Commercial Bank"
    notification_package_allowlist: str = "com.combank.combankdigital,combank.com.combankdigital"
    device_heartbeat_stale_minutes: int = 45
    # ComBank WhatsApp Automation
    combank_nic: str = ""
    combank_whatsapp_number: str = ""
    whatsapp_bot_url: str = "http://localhost:3100"
    whatsapp_bot_secret: str = ""

    # Phase 2: receipt extraction.
    extraction_enabled: bool = False
    ocr_engine: str = "none"          # none | paddle | tesseract
    ai_provider: str = "none"         # none | gemini | anthropic | ollama | local
    anthropic_api_key: str = ""
    anthropic_model: str = "claude-sonnet-5-5"
    # Google Gemini (Free tier, 0 GPU, 0 RAM)
    gemini_api_key: str = ""
    gemini_api_keys: str = ""         # Comma-separated keys for rotation to spread rate limits
    gemini_model: str = "gemini-2.5-flash"
    gemini_rate_limit_rpm: int = 14   # Safety ceiling below 15 RPM free tier limit
    auto_approve_high_confidence: bool = False  # If True, high-confidence AI verification can auto-approve
    confidence_threshold_high: float = 0.85
    confidence_threshold_medium: float = 0.50
    # Self-hosted local model via Ollama (low RAM, CPU-compatible)
    ollama_url: str = "http://localhost:11434"
    ollama_model: str = "llama3.2-vision"
    ai_timeout_seconds: int = 60

    # Cross-platform Integration API
    external_api_key: str = ""
    external_webhook_url: str = ""
    external_webhook_secret: str = ""
    site_url: str = "http://localhost:8000"
    auto_receipt_to_ledger: bool = True
    bot_sync_on_claim: bool = False

    # Supabase (Free tier or Self-hosted)
    supabase_url: str = ""
    supabase_service_key: str = ""
    supabase_sync_enabled: bool = False


settings = Settings()
