<?php

define('DEBUG', false);

ob_flush();
ob_start();

function display_dump($content)
{
    //var_dump($content);
    if (is_array($content)) {
        foreach ($content as $element) {
            echo $element."\n";
        }
    } else {
        echo $content."\n";
    }
}

require 'config/config.php';

$github=array(
    "event" => null,
    "payload" => null,
    "ref" => null,
    "repository"=> array(
        "name" => null
    )
);

$travis_config=array(
    "url" => "https://api.travis-ci.com/repo/AlternC%2Fdeb-builder/requests",
    "headers" => array(
        "Content-Type: application/json",
        "Accept: application/json",
        "Travis-API-Version: 3",
        "Authorization: token ".$travis_token
    ),
    "body" => array(
        "config" => array(
            "env" => array(
                    "global" => array(
                    "REPO_TO_BUILD=null",
                    "BRANCH_TO_BUILD=main",
                    "FORCE_BUILD=false"
                 )
             )
        )
    )
);


if (!isset($_SERVER["HTTP_X_GITHUB_EVENT"]) && !isset($_SERVER["HTTP_X_GITLAB_EVENT"])) {
    deny_request("Is it CI/CD event ?");
}

$github['event']=$_SERVER['HTTP_X_GITHUB_EVENT'] ?? $_SERVER["HTTP_X_GITLAB_EVENT"];

if (!in_array($github['event'], ["push","Push Hook","Tag Push Hook"])) {
    deny_request("Invalid event");
}

if (!verify_signature(file_get_contents('php://input'), $webhook_secret, $webhook_token)) {
    deny_request("Invalid signature");
}

$payload=json_decode($_REQUEST['payload']) ?? json_decode(file_get_contents('php://input'));

if (empty($payload)) {
    deny_request('payload invalid or missing');
}

$github['ref'] = $payload->ref;
$github['repository']['name'] = $payload->repository->full_name ?? $payload->repository->git_http_url;


if (!preg_match('#^[aA]ltern[cC]/(alternc-?|AlternC)#', $github['repository']['name']) && !preg_match('#^https?://#', $github['repository']['name']) ) {
    deny_request('project not allowed');
}

if (!preg_match('#(refs/heads/main)|(refs/tags/[\d\.]+)#', $github['ref'])) {
        deny_request('branch not allowed to be build');
}

trig_travis($travis_config, $github['repository']['name']);

echo "\nI was payloaded";

// Method to use

function deny_request($message ="Invalid request")
{
    header("HTTP/1.1 412 Precondition Failed");
    $content = ob_get_flush();
    if (defined('DEBUG')) {
        file_put_contents("/tmp/dump.txt", $content);
    }
    echo $content;
    die($message);
}

function verify_signature($payload, $webhook_secret, $webhook_token)
{

    if (!empty($_SERVER['HTTP_X_GITLAB_TOKEN']) && $_SERVER['HTTP_X_GITLAB_TOKEN'] === $webhook_token ) {
        return true;
    }

    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'];


    if (empty($signature)) {
        return false;
    }

        $signature_parts = explode('=', $signature);

    if (count($signature_parts) != 2) {
            return false;
    }

        $known_signature = hash_hmac($signature_parts['0'], $payload, $webhook_secret);

        return hash_equals($known_signature, $signature_parts[1]);
}


function trig_travis($travis,$repository)
{

    /*
    curl -s -X POST  -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Travis-API-Version: 3" \
        -H "Authorization: token $travis['token'] \
        -d "$body" \
        https://api.travis-ci.com/repo/AlternC%2Fdeb-builder/requests
    */

    if (str_starts_with($repository, "https://")) {
        $travis['body']['config']['env']['global'][0]="REPO_URL_TO_BUILD=$repository";
    } else {
        $travis['body']['config']['env']['global'][0]="REPO_TO_BUILD=$repository";
    }
    $params = json_encode($travis['body'], JSON_NUMERIC_CHECK);

    $options = array(
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $travis['headers'],
    CURLOPT_POSTFIELDS => $params,
    CURLOPT_URL => $travis['url'],
    CURLOPT_RETURNTRANSFER=> true
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);

    //Trig travis
    $result = curl_exec($ch);
    display_dump($result);

        // Freeing curl resource
    curl_close($ch);
}
