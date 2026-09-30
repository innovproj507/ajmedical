<?php

namespace AJM\Core;

/**
 * AuditLogger — registra inserts/updates/deletes en audit_log.
 * Usado por Repository::save()/delete() para dar trazabilidad
 * (tabla, registro, acción, antes/después) que un CRUD genérico no trae de fábrica.
 */
class AuditLogger
{
    public static function log(string $tabla, string $registroId, string $accion, ?array $antes, ?array $despues): void
    {
        $user = Auth::user();

        try {
            Database::getInstance()->execute(
                "INSERT INTO audit_log (tabla, registro_id, accion, datos_anteriores, datos_nuevos, usuario, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $tabla,
                    $registroId,
                    $accion,
                    $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
                    $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                    $user['username'] ?? 'sistema',
                    Security::getClientIP(),
                ]
            );
        } catch (\Throwable $e) {
            error_log('[AuditLogger] ' . $e->getMessage());
        }
    }
}
