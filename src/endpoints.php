<?php


function getToken(): void
{
    if (isset($_SESSION['session_start']) === false || $_SESSION['access_expiration'] < time()) {
        $url = "https://accounts.spotify.com/api/token";
        $request = \Httpful\Request::init()
            ->method('POST')
            ->uri($url)
            ->followRedirects(true)
            ->withoutStrictSSL();
        // Headers.
        $request->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->addHeader('Accept', '*/*');
        // Body.
        $body = "grant_type=client_credentials&client_id=".$_ENV['SPOTIFY_CID']."&client_secret=".$_ENV['SPOTIFY_SEC'];
        $request->body($body);
        // Envio de la request.
        $response = $request->send();
        // Verificar si la respuesta fue exitosa.
        if ($response->code === 200) {
            // Controlamos el body.
            $responseBody = $response->body;
            var_dump($responseBody);
            // Establecemos los datos de conexion.
            $_SESSION['session_start'] = true;
            $accessToken = $responseBody->access_token ?? '';
            $tokenType = $responseBody->token_type ?? '';
            $_SESSION['access_expiration'] = $tokenExpiration = time() + ((int) $responseBody->expires_in ?? 0);
            $_SESSION['access_chain'] = "$tokenType $accessToken";
        } else {
            var_dump($response->code);
            exit('ERROR TOKEN');
        }
    } else {
        var_dump("no hacia falta");
    }
}


function getPlaylists(bool $return = false, bool $conCanciones = false): string
{
    $output = '';
    if (noAccessChain() === true) {
        return retryRequest(__FUNCTION__, $return);
    } else {
        // Creamos el objeto con los datos para hacer la peticion.
        $data = [
            'url' => "https://api.spotify.com/v1/users/".$_ENV['SPOTIFY_UID']."/playlists",
            'headers' => authorizationChain(),
        ];
        // Creamos la peticion.
        $request = requestConstructor($data);
        // Envio de la request.
        $response = $request->send();
        var_dump($response->code);
        if ($response->code === 200) {
            var_dump($response->body);
            foreach($response->body->items as $linea) {
                if ($return === true) {
                    $output .= "\n\n\n".$linea->name;
                    $output .= "\n\tID:   ".$linea->id;
                    $output .= "\n\tRuta: ".$linea->href;
                } else {
                    echo '<h1>'.$linea->name.'</h1>';
                    echo "ID:   ".$linea->id.'<br>';
                    echo "Ruta: ".$linea->href.'<br>';
                    if ($conCanciones === true) {
                        getTracks($linea->id);
                    }
                }
            }
            var_dump("output");
            var_dump($output);
            var_dump("output del output");
            return $output;
        }
    }
    return $output;
}


function getTracks(string $idPlaylist): void
{
    if (noAccessChain() === true) {
        retryRequest(__FUNCTION__);
    } else {
        // Creamos el objeto con los datos para hacer la peticion.
        $data = [
            'url' => "https://api.spotify.com/v1/playlists/{$idPlaylist}/tracks?items(added_by.id,track(name,href,id,album(name,href)))",
            'headers' => authorizationChain(),
        ];
        // Creamos la peticion.
        $request = requestConstructor($data);
        // Envio de la request.
        $response = $request->send();
        if ($response->code === 200) {
            foreach($response->body->items as $linea) {
                echo "\t<h3>".$linea->track->name.'</h3>';
                echo "\t\t - Album:   ".$linea->track->album->name.'<br>';
                echo "\t\t - Ruta: ".$linea->track->href.'<br>';
                echo "\t\t - ID: ".$linea->track->id.'<br>';
            }
        }
    }
}