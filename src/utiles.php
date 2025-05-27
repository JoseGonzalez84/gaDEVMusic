<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

define('OUTPUT_FORMATS', [
    'mp3' => '-x --audio-format mp3',
    'webm' => '-f webm',
    'mp4' => '-f mp4',
]);


function noAccessChain(): bool
{
    return (isset($_SESSION['access_chain']) === false || empty($_SESSION['access_chain']) === true || $_SESSION['access_expiration'] < time());
}


function timeNow(string $format = 'r'): string
{
    return date($format);
}


function retryRequest(string $function, mixed $data = null): mixed
{
    getToken();
    sleep(1);
    if ($data === null) {
        return $function();
    } else {
        return $function($data);
    }
}


function authorizationChain(): array
{
    return ['Authorization' => $_SESSION['access_chain']];
}


function requestConstructor(array $data): Httpful\Request
{
    // Extraccion de todos los datos.
    extract($data);
    // Definicion del método por defecto.
    if (isset($method) === false || empty($method) === true) {
        $method = 'GET';
    }
    // Creacion del objeto.
    $request = \Httpful\Request::init()
        ->method($method)
        ->uri($url)
        ->followRedirects(true)
        ->withoutStrictSSL();
    // Headers.
    $request->addHeader('Accept', '*/*');
    // Comprobación de que haya mas headers.
    if (isset($headers) === true) {
        foreach ($headers as $keyHeader => $valueHeader) {
            $request->addHeader($keyHeader, $valueHeader);
        }
    }
    // Body.
    if (isset($body) === true) {
        if (is_array($body) === true && empty($body) === false) {
            $bodyToAttach = '?';
            foreach ($body as $keyBody => $valueBody) {
                $bodyToAttach .= $keyBody . "=" . $valueBody . "&";
            }

            $body = $bodyToAttach;
        }

        $request->body($body);
    }

    return $request;
}


function escribirLog(string $texto): void
{
    $ruta = RUTA_HOST . '/logs/';
    $fichero = FICHERO_LOG;
    if (file_exists($ruta)) {
        // si el fichero existe - borrarlo primero
        $ruta .= $fichero;
        if (file_exists($ruta)) {
            $fichero = fopen($ruta, 'a');
        } else {
            $fichero = fopen($ruta, 'w');
        }
        fwrite($fichero, "[" . date("Y.m.d H:i:s") . "] > " . $texto . "\n");
        fclose($fichero);
    }
}


function logUsuario(Nutgram $bot, string $mensaje): void
{
    // Definimos variables.
    $usuario = getNombreUsuario($bot);
    $userId = getIdUsuario($bot);
    $texto = "[USR][$usuario:$userId] > " . $mensaje;
    escribirLog($texto);
    //logData($userId, $texto);
}


function logServicio(string $mensaje): void
{
    // Definimos variables.
    $texto = "[SYS] > $mensaje";
    escribirLog($texto);
}


function getNombreUsuario(Nutgram $bot): string
{
    $nombre = '';
    $objetoUsuario = $bot->user();
    if (isset($objetoUsuario->username) === true) {
        $nombre = $objetoUsuario->username;
    }
    return $nombre;
}


function getIdUsuario(Nutgram $bot): string
{
    $id = '';
    $objetoUsuario = $bot->user();
    if (isset($objetoUsuario->username) === true) {
        $id = $objetoUsuario->id;
    }

    return $id;
}


function buildYtDlpQuery(array $parametros): string
{
    extract($parametros);

    $startStop = '';
    $outputFormat = '';

    if (isset($format) === true || in_array($format, OUTPUT_FORMATS) === true) {
        $outputFormat = OUTPUT_FORMATS[$format];
    }

    if (isset($startPosition) === true) {
        $startStop .= '--start ' . $startPosition;
    }

    if (isset($endPosition) === true) {
        $startStop  .= ' --end ' . $endPosition;
    }

    return sprintf(
        "yt-dlp -o /tmp/'%(title)s'.mp3 %s %s %s",
        $outputFormat,
        $startStop,
        $url
    );
}


function checkIsAlreadyRunning(): bool
{
    exec('ps -ax | grep -i ' . NOMBRE_SCRIPT_EXE . ' | grep -v grep', $salida);
    return count($salida) > 2;
}


function checkExecDependency(string $executable): bool
{
    exec('which ' . $executable, $salida);
    return count($salida) >= 1;
}


function getLogs(): string
{
    $salida = '';
    try {
        exec('tail -15 ' . RUTA_HOST . '/logs/' . FICHERO_LOG, $salida);
        $salida = implode("\n", $salida);
    } catch (Exception $ex) {
        logServicio("No se pueden extraer logs: " . $ex->getMessage());
        $salida = "No se pudieron traer logs.";
    }

    return $salida;
}


function validaAdmin(Nutgram $bot, string $clave = '')
{
    $validar = $_ENV['TELEBOT_ADMIN'] === getIdUsuario($bot);
    if ($validar === true && empty($clave) === false) {
        $validar = $_ENV['TELEBOT_ADMIN_KEY'] === $clave;
    }

    return $validar;
}


function ejecucionDescarga(Nutgram $bot, string $texto)
{
    //$bot->sendMessage("¡ Estoy resolviendo el problema de YouTube, dentro de poco volverá a funcionar !");
    logUsuario($bot, "Solicita una descarga. URL: https://$texto");
    $bot->sendMessage("🛫 Vamos allá! ");
    try {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );

        $nombreFichero = "'%(title)s'.mp3";
        $rutaDescarga = "-o /tmp/$nombreFichero";
        $rutaCookies = "--cookies-from-browser firefox --cookies /var/www/html/spotify_get/db/cookies.txt";
        $rutaCookies = "";
        $completeExec = 'yt-dlp -x ' . $rutaDescarga . ' --restrict-filenames --embed-thumbnail --embed-metadata ' . $rutaCookies . ' --audio-format mp3 ' . $texto;
        logUsuario($bot, "exec -> $completeExec");
        $process = proc_open($completeExec, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $ficheros = [];
            $informadoPlaylist = false;
            $yaDescargado = false;
            while ($line = fgets($pipes[1])) {
                logUsuario($bot, "YT-DLP -> $line");
                // Evitamos la duplicidad.
                if ($yaDescargado === false) {
                    if (str_contains($line, '[ExtractAudio] Destination: ') === true && $yaDescargado === false) {
                        $bot->sendMessage("🎶 Convirtiendo en audio");
                        $ficheros[] = explode('[ExtractAudio] Destination: ', $line);
                        $yaDescargado = true;
                    } elseif (str_contains($line, "[Metadata]") === true && $yaDescargado === false) {
                        $pattern = '/"(.*?)"/';
                        if (preg_match($pattern, $line, $matches)) {
                            $bot->sendMessage("🎶 Convirtiendo en audio");
                            logUsuario($bot, "MATCHES -> ".print_r($matches, true));
                            $ficheros[] = $matches[1];
                            $yaDescargado = true;
                        }
                    }
                } 
                
                if (str_contains($line, 'ERROR:') === true) {
                    $bot->sendMessage("Hay algun error con un elemento;");
                }

                if (str_contains($texto, 'playlist') === true) {
                    if ($informadoPlaylist === false) {
                        $informadoPlaylist = true;
                        $bot->sendMessage("🚀 Procesando la playlist.");
                        sleep(3);
                        $bot->sendMessage("⏱️ Esto puede tardar un rato... Si hubiera algún problema, el sistema te avisará.");
                    }
                } else {
                    if (str_contains($line, 'ERROR: [DRM]') === true) {
                        $bot->sendMessage("🚫 Enlace con protección DRM. No permitido.");
                    }
                }
                flush();
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $return_value = proc_close($process);
            $errores = [];
            if ((int) $return_value === 0) {
                try {
                    if (empty($ficheros) === true) {
                        throw new Exception("No hay ficheros que procesar");
                    }
                    foreach ($ficheros as $fichero) {
                        logUsuario($bot, "Fichero: ".print_r($fichero, true));
                        if (is_array($fichero) === true) {
                            $ficheroRutaMP3 = trim($fichero[1]);
                        } else {
                            $ficheroRutaMP3 = trim($fichero);
                        }
                        logUsuario($bot, "Check ruta fichero -> $ficheroRutaMP3 -> ".(file_exists($ficheroRutaMP3) ? "EXISTE." : "NO EXISTE."));
                        if (file_exists($ficheroRutaMP3) === true) {
                            $ficheroMP3 = fopen($ficheroRutaMP3, 'r+');
                            $bot->sendMessage("🚚 Enviando el fichero");
                            $bot->sendAudio(audio: InputFile::make($ficheroMP3), caption: "🎉 ¡Que lo disfrutes!");
                            logUsuario($bot, "Recibe el fichero: $ficheroRutaMP3.");
                            unlink($ficheroRutaMP3);
                        } else {
                            throw new Exception("Error con el fichero: $ficheroRutaMP3");
                        }
                    }
                } catch (\Exception $ex) {
                    $errores[] = $ex->getMessage();
                }
                // Si hubo errores, notificarlo.
                if (empty($errores) === false) {
                    foreach ($errores as $error) {
                        logUsuario($bot, "ERROR: " . print_r($error, true));
                    }
                }
            }
        }
    } catch (Exception $ex) {
        logUsuario($bot, "ERROR: " . $ex->getMessage());
        logServicio("ERROR Obtencion fichero: " . $ex->getMessage());
        $bot->sendMessage("😵‍💫 Algo salió mal: " . $ex->getMessage() . ".");
    }
}
