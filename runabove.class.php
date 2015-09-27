<?php

/**
* Copyright (c) 2015, Samuel Vilar Da Silva.
* All rights reserved.
*/

namespace Runabove;

class Runabove {

	private $auth_server = 'https://api.runabove.com/1.0/';		
	private $auth_time;
	private $auth_time_delta;
	private $auth_error = false;

	private $auth_app;
	private $auth_consumer;
	private $auth_private;
	private $auth_project;
	private $auth_signature;

	public function __construct($auth_app, $auth_private, $auth_consumer = null, $auth_project = null)
	{
		$this->auth_app = $auth_app;
		$this->auth_private = $auth_private;
		
		if (!empty($auth_consumer))
		{
			$this->auth_consumer = $auth_consumer;
		}
				

		if (!empty($auth_project))
		{
			$this->auth_project = $auth_project;
		}
	}	

	private function time()
	{
		return $this->call("GET", "time");
	}

	private function signature($method, $url, $body = null)
	{
		if ($url != 'credential' && (empty($this->auth_consumer) || empty($this->auth_private)))
		{
			echo 'Runabove : Application Private and Cusumer Key is required !';
			return false;
		}
		else if ($this->auth_error)
		{
			echo 'Runabove : Error Auth !';
			return false;
		}

		$time = $this->time();
		if (!isset($this->auth_time_delta))
		{
			$this->auth_time_delta = $time - time();
		}
		$this->auth_time = time() + $this->auth_time_delta;

		if(!empty($body))
		{
			$body = json_encode($body);
		}

		$signature = $this->auth_private."+".$this->auth_consumer."+".$method."+".$this->auth_server.$url."+".$body."+".$this->auth_time;
		$signature = "$1$".sha1($signature);

		$this->auth_signature = $signature;
	}

	private function call($method, $url, $body = null)
	{
		if (empty($this->signature) && $url != "time")
		{
			$this->signature($method, $url, $body);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->auth_server.$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-Ra-Application: ".$this->auth_app,
			"X-Ra-Timestamp: ".$this->auth_time,
			"X-Ra-Signature: ".$this->auth_signature,
			"X-Ra-Consumer: ".$this->auth_consumer,
		));

		if ($body)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		} 

		$result = curl_exec($ch);

		if ($result === false)
		{
			print_r(curl_errno($ch));
			print_r(curl_error($ch));
			return false;
		}

		curl_close($ch);

		$return = json_decode($result, true);

		if (!$return)
		{
			return false;
		}
		else if(isset($return['message']))
		{
			echo 'Runabove : Error (' . $return['message'].')';
			return false;
		}
		else
		{
			return $return;
		}
	}

	public function credential($rules, $redirect)
	{
		$body = array(
			'accessRules' => $rules,
			'redirection' => $redirect,
		);
		return $this->call("POST", "auth/credential", $body);
	}

	public function project()
	{
		$project = $this->call("GET", "project");
		return $project[0];
	}

	public function regions()
	{
		return $this->call("GET", "region");
	}

	public function sshKey()
	{
		return $this->call("GET", "ssh");
	}

	public function flavor($region = null)
	{
		return $this->call("GET", "flavor?&region=".$region);
	}

	public function images($flavor_id = null, $region = null)
	{
		return $this->call("GET", "image?flavorId=".$flavor_id.'&region='.$region);
	}

	public function createInstance($flavor_id, $image_id, $name, $region, $sshKeyName = null)
	{
		$body = [
			"flavorId" => $flavor_id,
			"imageId" => $image_id,
			"name" => $name,
			"region" => $region,
		];
		return $this->call("POST", "instance", $body);
	}

	public function deleteInstance($instance_id)
	{
		$body = [
			"instanceId" => $instance_id
		];
		return $this->call("DELETE", "instance/".$instance_id, $body);
	}

	public function listInstance()
	{
		return $this->call("GET", "instance");
	}
}
