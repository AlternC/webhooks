<?php

include('config/config.php');

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

if (!isset($_SERVER["HTTP_X_GITHUB_EVENT"])) {
	deny_request("Is it CI/CD event ?");
}

$github['event']=$_SERVER['HTTP_X_GITHUB_EVENT'];

if ($github['event'] != "push") {
	deny_request("Invalid event");
}

$payload=json_decode($_REQUEST['payload']);

if (empty($payload)) {
	deny_request('payload invalid or missing');
}

$github['ref'] = $payload->ref;
$github['repository']['name'] = $payload->repository->full_name;

if (!preg_match('#^[aA]ltern[cC]/(alternc-?|AlternC)#',$github['repository']['name'] )) {
	deny_request('project not allowed');
}

if (!preg_match('#(refs/heads/main)|(refs/tags/[\d\.]+)#',$github['ref'])) {
        deny_request('branch not allowed to be build');
}

trig_travis($travis_config,$github['repository']['name']);

echo "\nI was payloaded";


// Method to use

function deny_request($message ="Invalid request") {
	header("HTTP/1.1 412 Precondition Failed");
	die($message);
}

function trig_travis($travis,$repository) {

/*
    curl -s -X POST  -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Travis-API-Version: 3" \
        -H "Authorization: token $travis['token'] \
        -d "$body" \
        https://api.travis-ci.com/repo/AlternC%2Fdeb-builder/requests
*/

	$travis['body']['config']['env']['global'][0]="REPO_TO_BUILD=$repository";
	$params = json_encode($travis['body'],JSON_NUMERIC_CHECK);

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
	var_dump($result);

        // Freeing curl resource
	curl_close($ch);
}
