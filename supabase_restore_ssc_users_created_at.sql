BEGIN;

ALTER TABLE public.ssc_users
    ADD COLUMN IF NOT EXISTS created_at timestamp without time zone;

UPDATE public.ssc_users
SET created_at = CURRENT_TIMESTAMP
WHERE created_at IS NULL;

ALTER TABLE public.ssc_users
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP,
    ALTER COLUMN created_at SET NOT NULL;

COMMIT;
