"""combank receipt and imported_by nullable

Revision ID: 99677c6a441f
Revises: 63fb427b1aa5
Create Date: 2026-11-06 00:12:34.388321

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = '99677c6a441f'
down_revision: Union[str, Sequence[str], None] = '63fb427b1aa5'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema."""
    with op.batch_alter_table('bank_statement_imports') as batch_op:
        batch_op.alter_column('imported_by',
                   existing_type=sa.INTEGER(),
                   nullable=True)

    with op.batch_alter_table('sms_events') as batch_op:
        batch_op.add_column(sa.Column('receipt_stan', sa.String(length=64), nullable=True))
        batch_op.add_column(sa.Column('receipt_pdf_key', sa.String(length=255), nullable=True))
        batch_op.add_column(sa.Column('receipt_data', sa.JSON(), nullable=True))
        batch_op.create_index(batch_op.f('ix_sms_events_receipt_stan'), ['receipt_stan'], unique=False)


def downgrade() -> None:
    """Downgrade schema."""
    with op.batch_alter_table('sms_events') as batch_op:
        batch_op.drop_index(batch_op.f('ix_sms_events_receipt_stan'))
        batch_op.drop_column('receipt_data')
        batch_op.drop_column('receipt_pdf_key')
        batch_op.drop_column('receipt_stan')

    with op.batch_alter_table('bank_statement_imports') as batch_op:
        batch_op.alter_column('imported_by',
                   existing_type=sa.INTEGER(),
                   nullable=False)
