<?php
/**
 * Middleware de Autenticación
 */

class AuthMiddleware 
{
    public function handle() 
    {
        // Verificar autenticación
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(["error" => "No autorizado"]);
            return false;
        }
        
        return true;
    }
    
    private function isAuthenticated() 
    {
        // Implementar lógica de autenticación
        // Por ahora, verificar session o token básico
        return isset($_SESSION["user_id"]) || $this->validateApiToken();
    }
    
    private function validateApiToken() 
    {
        $token = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
        $token = str_replace("Bearer ", "", $token);
        
        // Validar token (implementar según necesidades)
        return !empty($token);
    }
}