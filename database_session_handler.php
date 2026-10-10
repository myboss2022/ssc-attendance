<?php
final class SupabaseSessionHandler implements SessionHandlerInterface
{
    private $pdo;
    private $lockedSessionId;

    public function open($path, $name): bool
    {
        $host = getenv('SUPABASE_DB_HOST');
        $database = getenv('SUPABASE_DB_NAME');
        $user = getenv('SUPABASE_DB_USER');
        $password = getenv('SUPABASE_DB_PASSWORD');
        $port = getenv('SUPABASE_DB_PORT') ?: '5432';
        $sslmode = getenv('SUPABASE_DB_SSLMODE') ?: 'require';

        if (!$host || !$database || !$user || $password === false) {
            throw new RuntimeException('Supabase session storage is missing database configuration.');
        }
        if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('The PHP pdo_pgsql extension is required for Supabase sessions.');
        }

        $this->pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}",
            $user,
            $password,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false)
        );

        return true;
    }

    public function close(): bool
    {
        $this->unlock();

        $this->pdo = null;
        return true;
    }

    public function read($id): string
    {
        $this->lock($id);
        $statement = $this->pdo->prepare('SELECT session_data FROM public.app_sessions WHERE session_id = ?');
        $statement->execute(array($id));
        $data = $statement->fetchColumn();
        return $data === false ? '' : $data;
    }

    public function write($id, $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO public.app_sessions (session_id, session_data, last_activity)
             VALUES (?, ?, ?)
             ON CONFLICT (session_id) DO UPDATE
             SET session_data = EXCLUDED.session_data, last_activity = EXCLUDED.last_activity'
        );
        return $statement->execute(array($id, $data, time()));
    }

    public function destroy($id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM public.app_sessions WHERE session_id = ?');
        return $statement->execute(array($id));
    }

    public function gc($max_lifetime): int|false
    {
        $statement = $this->pdo->prepare('DELETE FROM public.app_sessions WHERE last_activity < ?');
        $statement->execute(array(time() - $max_lifetime));
        return $statement->rowCount();
    }

    private function lock($id): void
    {
        if ($this->lockedSessionId === $id) {
            return;
        }

        $this->unlock();
        $statement = $this->pdo->prepare('SELECT pg_advisory_lock(hashtextextended(?, 0))');
        $statement->execute(array($id));
        $this->lockedSessionId = $id;
    }

    private function unlock(): void
    {
        if ($this->pdo instanceof PDO && $this->lockedSessionId !== null) {
            $statement = $this->pdo->prepare('SELECT pg_advisory_unlock(hashtextextended(?, 0))');
            $statement->execute(array($this->lockedSessionId));
            $this->lockedSessionId = null;
        }
    }
}
