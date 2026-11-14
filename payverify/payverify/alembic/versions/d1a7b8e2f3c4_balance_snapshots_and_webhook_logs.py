"""balance snapshots and webhook logs

Revision ID: d1a7b8e2f3c4
Revises: fc5dce840963
Create Date: 2026-11-12 15:05:00.000000

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = 'd1a7b8e2f3c4'
down_revision: Union[str, Sequence[str], None] = 'fc5dce840963'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # 1. bank_balance_snapshots
    op.create_table(
        'bank_balance_snapshots',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('source', sa.String(length=32), nullable=False),
        sa.Column('account_mask', sa.String(length=64), nullable=True),
        sa.Column('account_tail', sa.String(length=16), nullable=True),
        sa.Column('current_balance_minor', sa.BigInteger(), nullable=True),
        sa.Column('available_balance_minor', sa.BigInteger(), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )
    with op.batch_alter_table('bank_balance_snapshots', schema=None) as batch_op:
        batch_op.create_index(batch_op.f('ix_bank_balance_snapshots_account_tail'), ['account_tail'], unique=False)
        batch_op.create_index(batch_op.f('ix_bank_balance_snapshots_created_at'), ['created_at'], unique=False)

    # 2. webhook_delivery_logs
    op.create_table(
        'webhook_delivery_logs',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('event', sa.String(length=64), nullable=False),
        sa.Column('claim_id', sa.Integer(), nullable=True),
        sa.Column('order_id', sa.Integer(), nullable=True),
        sa.Column('url', sa.String(length=500), nullable=False),
        sa.Column('status_code', sa.Integer(), nullable=True),
        sa.Column('response_body', sa.Text(), nullable=True),
        sa.Column('attempts', sa.Integer(), nullable=False),
        sa.Column('delivered', sa.Boolean(), nullable=False),
        sa.Column('error_message', sa.Text(), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.ForeignKeyConstraint(['claim_id'], ['payment_claims.id']),
        sa.ForeignKeyConstraint(['order_id'], ['orders.id']),
        sa.PrimaryKeyConstraint('id')
    )
    with op.batch_alter_table('webhook_delivery_logs', schema=None) as batch_op:
        batch_op.create_index(batch_op.f('ix_webhook_delivery_logs_claim_id'), ['claim_id'], unique=False)
        batch_op.create_index(batch_op.f('ix_webhook_delivery_logs_order_id'), ['order_id'], unique=False)
        batch_op.create_index(batch_op.f('ix_webhook_delivery_logs_delivered'), ['delivered'], unique=False)
        batch_op.create_index(batch_op.f('ix_webhook_delivery_logs_created_at'), ['created_at'], unique=False)


def downgrade() -> None:
    with op.batch_alter_table('webhook_delivery_logs', schema=None) as batch_op:
        batch_op.drop_index(batch_op.f('ix_webhook_delivery_logs_created_at'))
        batch_op.drop_index(batch_op.f('ix_webhook_delivery_logs_delivered'))
        batch_op.drop_index(batch_op.f('ix_webhook_delivery_logs_order_id'))
        batch_op.drop_index(batch_op.f('ix_webhook_delivery_logs_claim_id'))
    op.drop_table('webhook_delivery_logs')

    with op.batch_alter_table('bank_balance_snapshots', schema=None) as batch_op:
        batch_op.drop_index(batch_op.f('ix_bank_balance_snapshots_created_at'))
        batch_op.drop_index(batch_op.f('ix_bank_balance_snapshots_account_tail'))
    op.drop_table('bank_balance_snapshots')

