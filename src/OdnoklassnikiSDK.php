<?php

namespace Apiok;

class OdnoklassnikiSDK {
    const PARAMETER_NAME_ACCESS_TOKEN = "access_token";
    const PARAMETER_NAME_REFRESH_TOKEN = "refresh_token";
    const PARAMETER_NAME_AUTHORIZATION_CODE = "authorization_code";
    private static $app_id = "";
    private static $app_public_key = "";
    private static $app_secret_key = "";
    private static $redirect_url = "";
    private static $scope = "";
    private static $AUTHORIZE_ADDRESS = "https://connect.ok.ru/oauth/authorize";
    private static $TOKEN_SERVICE_ADDRESS = "https://api.ok.ru/oauth/token.do";
    private static $API_REQUEST_ADDRESS = "https://api.ok.ru/fb.do";
    private static $access_token;
    private static $refresh_token;

    public static function setParameters(array $parameters = array()) {
        self::$app_id = $parameters['app_id'];
        self::$app_public_key = $parameters['app_public_key'];
        self::$app_secret_key = $parameters['app_secret_key'];
        self::$redirect_url = $parameters['redirect_url'];
        self::$scope = $parameters['scope'];
        if (isset($parameters['access_token'])){
            self::$access_token = $parameters['access_token'];
        }
    }
    
    public static function getAppId() {
        return self::$app_id;
    }
    
    public static function getRedirectUrl() {
        return self::$redirect_url;
    }
    
    public static function getCode() {
        if (!empty($_GET["code"])) {
            return $_GET["code"];
        }
        else {
            return null;
        }
    }
    
    public static function checkCurlSupport() {
        return function_exists('curl_init');
    }
    
    public static function changeCodeToToken($code) {
        $postFields = array(
          'code' => $code,
          'redirect_uri' => self::$redirect_url,
          'grant_type' => self::PARAMETER_NAME_AUTHORIZATION_CODE,
          'client_id' => self::$app_id,
          'client_secret' => self::$app_secret_key,
        );
        $query = http_build_query($postFields);
        $curl = curl_init(self::$TOKEN_SERVICE_ADDRESS . '?' . $query);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $s = curl_exec($curl);
        curl_close($curl);
        $a = json_decode($s, true);
        if (!empty($a[self::PARAMETER_NAME_ACCESS_TOKEN])) {
            self::$access_token = $a[self::PARAMETER_NAME_ACCESS_TOKEN];
        }
        if (!empty($a[self::PARAMETER_NAME_REFRESH_TOKEN])) {
            self::$refresh_token = $a[self::PARAMETER_NAME_REFRESH_TOKEN];
        }
        return !empty($a[self::PARAMETER_NAME_ACCESS_TOKEN]) && !empty($a[self::PARAMETER_NAME_REFRESH_TOKEN]);
    }
    
    public static function updateAccessTokenWithRefreshToken() {
        $postFields = array(
          'refresh_token' => self::$refresh_token,
          'grant_type' => self::PARAMETER_NAME_REFRESH_TOKEN,
          'client_id' => self::$app_id,
          'client_secret' => self::$app_secret_key,
        );
        $query = http_build_query($postFields);
        $curl = curl_init(self::$TOKEN_SERVICE_ADDRESS . '?' . $query);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $s = curl_exec($curl);
        curl_close($curl);
        $a = json_decode($s, true);
        if (empty($a[self::PARAMETER_NAME_ACCESS_TOKEN])) {
            return false;
        } else {
            self::$access_token = $a[self::PARAMETER_NAME_ACCESS_TOKEN];
            return true;
        }
    }
    
    public static function makeRequest($methodName, array $parameters = array()) {
        if (is_null(self::$app_id) || is_null(self::$app_public_key) || is_null(self::$app_secret_key) || is_null(self::$access_token) ) {
            return null;
        }
        if (!self::isAssoc($parameters)) {
            return null;
        }
        $parameters["application_key"] = self::$app_public_key;
        $parameters["method"] = $methodName;
        $parameters["sig"] = self::calcSignature($methodName, $parameters);
        $parameters[self::PARAMETER_NAME_ACCESS_TOKEN] = self::$access_token;
        $requestStr = http_build_query($parameters);
        $curl = curl_init(self::$API_REQUEST_ADDRESS . "?" . $requestStr);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $s = curl_exec($curl);
        curl_close($curl);
        return json_decode($s, true);
    }
    
    public static function getAuthorizeUrl() {
        $query = http_build_query(array(
            'client_id' => self::$app_id,
            'scope' => self::$scope,
            'response_type' => 'code',
            'redirect_uri' => self::$redirect_url
        ));
        
        return self::$AUTHORIZE_ADDRESS . '?' . $query;
    }
    
    private static function calcSignature($methodName, array $parameters = array()) {
        if (is_null(self::$app_id) || is_null(self::$app_public_key) || is_null(self::$app_secret_key) || is_null(self::$access_token) ) {
            return null;
        }
        if (!self::isAssoc($parameters)) {
            return null;
        }
        $parameters["application_key"] = self::$app_public_key;
        $parameters["method"] = $methodName;
        if (!ksort($parameters)) {
            return null;
        } else {
            $requestStr = "";
            foreach($parameters as $key=>$value) {
                $requestStr .= $key . "=" . $value;
            }
            $requestStr .= md5(self::$access_token . self::$app_secret_key);
            return md5($requestStr);
        }
    }
    
    private static function isAssoc($arr) {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
