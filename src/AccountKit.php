<?php

namespace WildWolf;

class AccountKit
{
    /**
     * @var string
     */
    private $app_id;

    /**
     * @var string
     */
    private $app_secret;

    /**
     * @var string
     */
    private $apiver = 'v1.2';

    public function __construct($appid, string $secret)
    {
        $this->app_id     = $appid;
        $this->app_secret = $secret;
    }

    public function apiVersion() : string
    {
        return $this->apiver;
    }

    public function setApiVersion(string $version)
    {
        $this->apiver = $version;
    }

    /**
     * @param string $url
     * @param string $method
     * @param mixed $data
     * @throws \Exception
     * @return mixed
     */
    private function makeRequest(string $url, $method = 'GET', $data = null)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype    = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if (200 === $code && false !== strpos($ctype, 'application/json')) {
            $response = json_decode($response);
            return $response;
        }

        if (false !== strpos($ctype, 'application/json')) {
            $response = json_decode($response);
            $error    = isset($response->error->message) ? $response->error->message : "";
            $code     = isset($response->error->code)    ? $response->error->code    : 0;
            throw new \Exception($error, $code);
        }

        throw new \Exception($response, $code);
    }

    /**
     * @see https://developers.facebook.com/docs/accountkit/graphapi#retrieving-user-access-tokens-with-an-authorization-code
     * @param string $authcode
     * @return mixed
     */
    public function getAccessToken(string $authcode)
    {
        $url = "https://graph.accountkit.com/{$this->apiver}/access_token?grant_type=authorization_code&code={$authcode}&access_token=AA|{$this->app_id}|{$this->app_secret}";
        return $this->makeRequest($url);
    }

    /**
     * @param string $token
     * @return string
     */
    private function getAppSecretProof(string $token) : string
    {
        return hash_hmac('sha256', $token, $this->app_secret);
    }

    /**
     * @see https://developers.facebook.com/docs/accountkit/graphapi#at-validation
     * @param string $token
     * @return mixed
     */
    public function validateAccessToken(string $token)
    {
        $proof = $this->getAppSecretProof($token);
        $url   = "https://graph.accountkit.com/{$this->apiver}/me/?access_token={$token}&appsecret_proof={$proof}";
        return $this->makeRequest($url);
    }

    /**
     * @param string $token
     * @return mixed
     */
    public function logout(string $token)
    {
        $proof = $this->getAppSecretProof($token);
        $url   = "https://graph.accountkit.com/{$this->apiver}/logout?access_token={$token}&appsecret_proof={$proof}";
        return $this->makeRequest($url, "POST");
    }

    /**
     * @param int $account_id
     * @return mixed
     */
    public function invalidateAllTokens($account_id)
    {
        $url = "https://graph.accountkit.com/{$this->apiver}/{$account_id}/invalidate_all_tokens?access_token=AA|{$this->app_id}|{$this->app_secret}";
        return $this->makeRequest($url, "POST");
    }
}
