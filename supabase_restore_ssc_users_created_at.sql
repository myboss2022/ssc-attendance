BEGIN;

ALTER TABLE public.ssc_users
    ADD COLUMN IF NOT EXISTS created_at timestamp without time zone;

UPDATE public.ssc_users
SET created_at = COALESCE(
    (
        SELECT MIN(al.created_at)
        FROM public.activity_logs al
        WHERE al.entity_type = 'ssc_user'
          AND al.entity_id = ssc_users.id
          AND al.action = 'SSC account creation'
    ),
    CURRENT_TIMESTAMP
)
WHERE created_at IS NULL;

ALTER TABLE public.ssc_users
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP,
    ALTER COLUMN created_at SET NOT NULL;

COMMIT;
