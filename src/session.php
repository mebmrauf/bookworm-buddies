<?php
class DatabaseSessionHandler implements SessionHandlerInterface {
    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        try {
            $res = run("SELECT data FROM sessions WHERE id = ? AND expires_at > NOW()", "s", $id)->get_result();
            $row = $res ? $res->fetch_row() : null;
            return $row ? (string) $row[0] : '';
        } catch (Throwable $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool {
        try {
            // Keep session active for 30 days
            run("
                INSERT INTO sessions (id, data, expires_at)
                VALUES (?, ?, NOW() + INTERVAL 30 DAY)
                ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)
            ", "ss", $id, $data);
            return true;
        } catch (Throwable $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool {
        try {
            run("DELETE FROM sessions WHERE id = ?", "s", $id);
            return true;
        } catch (Throwable $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        try {
            // Clean expired sessions occasionally
            $res = run("DELETE FROM sessions WHERE expires_at < NOW()");
            return $res->affected_rows ?? 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

