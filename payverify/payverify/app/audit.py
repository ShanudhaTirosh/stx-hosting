"""Append-only, hash-chained audit log. Each row commits to the previous row's hash."""
import hashlib
import json

from sqlalchemy import select
from sqlalchemy.orm import Session

from .models import AuditLog, utcnow

GENESIS = "0" * 64


def _digest(prev: str, ts, actor_type, actor_id, action, entity, entity_id, detail) -> str:
    payload = json.dumps([prev, ts.replace(tzinfo=None).isoformat(), actor_type, actor_id, action,
                          entity, entity_id, detail], sort_keys=True, default=str)
    return hashlib.sha256(payload.encode()).hexdigest()


def log(db: Session, *, action: str, entity: str, entity_id: int | None, actor_id: int | None = None,
        detail: dict | None = None) -> AuditLog:
    detail = detail or {}
    tail = db.execute(select(AuditLog).order_by(AuditLog.id.desc()).limit(1).with_for_update()).scalar_one_or_none()
    prev = tail.hash_self if tail else GENESIS
    ts = utcnow()
    actor_type = "user" if actor_id else "system"
    row = AuditLog(ts=ts, actor_type=actor_type, actor_id=actor_id, action=action, entity=entity,
                   entity_id=entity_id, detail=detail, hash_prev=prev,
                   hash_self=_digest(prev, ts, actor_type, actor_id, action, entity, entity_id, detail))
    db.add(row)
    db.flush()
    return row


def verify_chain(db: Session) -> tuple[bool, int | None]:
    prev = GENESIS
    for r in db.execute(select(AuditLog).order_by(AuditLog.id)).scalars():
        if r.hash_prev != prev or r.hash_self != _digest(prev, r.ts, r.actor_type, r.actor_id, r.action,
                                                          r.entity, r.entity_id, r.detail):
            return False, r.id
        prev = r.hash_self
    return True, None
