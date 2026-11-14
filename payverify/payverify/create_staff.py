"""Create a staff account (self-registration can only create customers).
Usage: python create_staff.py you@example.com 'Name' super_admin"""
import getpass
import sys

from app.db import Base, SessionLocal, engine
from app.models import Role, User
from app.security import hash_password

email, name, role = sys.argv[1], sys.argv[2], sys.argv[3]
assert role in {r.value for r in Role if r is not Role.customer}, "invalid staff role"
pw = getpass.getpass("Password (min 12 chars): ")
assert len(pw) >= 12
Base.metadata.create_all(engine)
with SessionLocal() as db:
    db.add(User(email=email.lower(), name=name, role=role, password_hash=hash_password(pw)))
    db.commit()
print("created", email, role)
