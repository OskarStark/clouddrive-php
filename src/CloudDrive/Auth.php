<?php

namespace CloudDrive;

use GuzzleHttp\Client;

class Auth extends Object
{
    protected $accessToken;

    protected $config = [];

    protected $email;

    protected $httpClient;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->httpClient = new Client();
    }

    public function authorize($email)
    {
        $this->email = $email;

        if (file_exists("{$this->config['tokens_directory']}{$this->email}.token")) {
            $token = json_decode(file_get_contents(APP_ROOT . "tokens/{$this->email}.token"), true);
            if (time() - $token['lastAuthorized'] > 60) {
                $token = $this->refreshToken($token['refresh_token']);
            }
        } else {
            $token = $this->getAuthorizationGrant();
        }

        $this->accessToken = $token['access_token'];
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getAuthorizationGrant()
    {
        echo "Navigate to the following URL and paste in the URL you are redirected to:\n";
        echo "https://www.amazon.com/ap/oa?client_id=amzn1.application-oa2-client.98cb6d1b9d304f08a2ccc8d59fb4e4e4&scope=clouddrive%3Aread%20clouddrive%3Awrite&response_type=code&redirect_uri=http://localhost\n";

        $handle = fopen("php://stdin", "r");
        $url = trim(fgets($handle));

        $info = parse_url($url);
        parse_str($info['query'], $query);

        if (!isset($query['code'])) {
            throw new \RuntimeException("No code exists in the redirect URL.");
        }

        $code = $query['code'];

        $request = $this->httpClient->createRequest('POST', 'https://api.amazon.com/auth/o2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => [
                'grant_type'    => "authorization_code",
                'code'          => $code,
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri'  => "http://localhost",
            ],
        ]);

        $response = $this->sendRequest($request);

        if ($response['success']) {
            $response['data']['lastAuthorized'] = time();
            file_put_contents(APP_ROOT . "tokens/{$this->email}.token", json_encode($response['data']));
        } else {
            throw new \Exception($response['data']['message']);
        }

        return $response;
    }

    public function refreshToken($refreshToken)
    {
        $request = $this->httpClient->createRequest('POST', 'https://api.amazon.com/auth/o2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type'    => "refresh_token",
                'refresh_token' => $refreshToken,
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri'  => "http://localhost",
            ],
        ]);

        $response = $this->sendRequest($request);

        if ($response['success']) {
            $response['data']['lastAuthorized'] = time();
            file_put_contents(APP_ROOT . "tokens/{$this->email}.token", json_encode($response['data']));
        } else {
            throw new \Exception("Unable to refresh authorization token: " . $response['data']['message']);
        }

        return $response;
    }
}