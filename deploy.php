<?php
// Define una clave secreta que solo tú y GitHub sepan
$secret_token = "RMtJCXa8neUo14w49jlXLvUb"; 

// Validamos si el token enviado en la URL coincide
if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso denegado. No tienes el token correcto.");
}

// Si el token es correcto, ejecutamos el despliegue
$ssh_command = "ssh -i /home/diffmerc/.ssh/id_ed25519 -o StrictHostKeyChecking=no";
$cmd = "GIT_SSH_COMMAND='$ssh_command' /usr/bin/git pull origin main 2>&1";

$output = shell_exec($cmd);

// Guardamos el log
file_put_contents('deploy.log', "[" . date('Y-m-d H:i:s') . "]\n" . $output . "\n", FILE_APPEND);

echo "Resultado: " . $output;
?>
