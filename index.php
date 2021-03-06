<?php

//NOTE
//NOTIFICATION TYPE
// 1: Like Post
// 2: Comment on a Post
// 3: Like Comment
// 4: Comment on a Post, which you've commented
// 5: Comment on a Post, which you've liked

//ini_set("display_startup_errors", "1");
//ini_set("display_errors", "1");
//error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');

class pookAPI{
	private $db;
	
	function __construct(){
		//Production Mode
		$this->db=new mysqli('localhost', 'root', '', 'pook_db');
		
		//Development Mode
		//$this->db=new mysqli('localhost', 'root', '', 'dbpook');

		if($this->db->connect_error)
		{
		    die("$mysqli->connect_errno: $mysqli->connect_error");
		}
	}

	function __destruct(){
		$this->db->close();
	}

	function index(){
		echo  '<h1>Welcome to Pook Web Services</h1>';
	}
	
	function find($field, $param){
		$query="";
		if ($field=="email")
		{
			$query="SELECT * FROM users WHERE email=? LIMIT 1";
		}
		else if ($field=="fbid")
		{
			$query="SELECT * FROM users WHERE fbid=? LIMIT 1";
		}
		
		$stmt=$this->db->prepare($query);
		$stmt->bind_param("s", $param);
		$stmt->execute();
		$result=$stmt->get_result();
		$row=$result->fetch_assoc();
		$stmt->close();
		
		return $row;
	}
	
	function check_email($emailid)
	{
		return $this->find("email", $emailid);
	}
	
	function check_fbid($fbid)
	{
		return $this->find("fbid", $fbid);		
	}
	
	function test()
	{
		$response=array();

		$query="SELECT * FROM users";
		$stmt=$this->db->prepare($query);
		//$stmt->bind_param("i", $userid);
		$stmt->execute();
		$result=$stmt->get_result();
		$data=$result->fetch_assoc();
		
		$response["data"] = $data;
		
		$response['status_code']=1;
		$response['message']='This is just a test.';
		
	
		echo json_encode($response);
	}
	
	function push_test() 
	{
		$device_token=isset($req_data['device_token']) ? $req_data['device_token'] : '8da404c7f780883591dba45e2a07ca0dc1ed666753770829e1de49e4939c0ef7';
		$message=isset($req_data['message']) ? $req_data['message'] : 'Test';
	
		$this->apns_push(array($device_token),true,$message);
		
		$response['status_code']=1;
		$response['message']='This is just a push notification test.';
		
		echo json_encode($response);
	}
	
	function signin()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$device_token=isset($req_data["device_token"]) ? $req_data["device_token"] : '';

		
		
		if (!is_null($device_token))
		{
			$query="SELECT * FROM users WHERE device_token=? LIMIT 1";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("s", $device_token);
			$stmt->execute();
			$result=$stmt->get_result();
			$row=$result->fetch_assoc();
			
			if (!is_null($row))
			{
				//Passwords Matching
				$current=date('Y-m-d H:i:s');
				$row['device_token']=$device_token;
				$row['last_login']=$current;
				
				$response['status_code']=1;
				$response['message']='success';
				$response['user']=$row;
				
				//Update device token of a user
				$userid=$row['id'];
				$query="UPDATE users SET device_token=?, last_login=? WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("ssi", $device_token, $current, $userid);
				$stmt->execute();
			}
			else
			{
				$created = date('Y-m-d H:i:s');
				$query = "INSERT INTO users (device_token, username, email, created, last_login, status) values (?, ?, ?, ?, ?, ?)";
				$stmt=$this->db->prepare($query);

				$username = '';
				$email = '';
				$status = 'active';

				$stmt->bind_param("ssssss", $device_token, $username, $email, $created, $created, $status);
				$stmt->execute();
				
				if ($stmt->affected_rows>=1)
				{
					$userid=$stmt->insert_id;

					$response['status_code']=1;
					$response['message']='success';
					$response['user']=array(
						'id' => $userid,
						'username' => '',
						'email' => '',
						'profile' => '',
						'username' => '',
						);
				}
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function signup()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
	
		$email = isset($req_data["email"]) ? $req_data["email"] : null;
		$password=isset($req_data['password']) ? $req_data['password'] : null;
		$phone_no= isset($req_data["phone_no"]) ? $req_data["phone_no"] : null;
		$device_token=isset($req_data["device_token"]) ? $req_data["device_token"] : '';
		$username= isset($req_data["username"]) ? $req_data["username"] : null;
		
		//Image uploading
		if (!isset($_FILES['profile']) || !isset($_FILES['profile']['type']))
		{
			$response['status_code']=2;
			$response['message']="You should upload a profile picture.";
			echo json_encode($response);
			return;
		}
		
		$allowedExts = array("gif", "jpeg", "jpg", "png");
		$extension = "jpg";//end(explode(".", $_FILES["photo"]["name"]));
		
		if ((($_FILES['profile']["type"] == "image/gif")
				|| ($_FILES['profile']["type"] == "image/jpeg")
				|| ($_FILES['profile']["type"] == "image/jpg")
				|| ($_FILES['profile']["type"] == "image/pjpeg")
				|| ($_FILES['profile']["type"] == "image/x-png")
				|| ($_FILES['profile']["type"] == "image/png"))
				&& in_array($extension, $allowedExts))
		{
			if ($_FILES['profile']["error"] > 0)
			{
				$profileurl = null;
			}
			else
			{
				$timemd5=md5(microtime());
				$filename = "profile".$timemd5.".".$extension;
				$profileurl = "profiles/" .$filename;
				$copied=move_uploaded_file($_FILES['profile']["tmp_name"],	$profileurl);			
			}
		}
		else
		{
			$profileurl = null;
		}
			
		if (!is_null($email) && !is_null($password) && !is_null($username))
		{
			$user=$this->check_email($email);
			
			if (!is_null($user))
			{
				//User with the specified email is existing.
				$response['status_code']=3;
				$response['message']="Email is already used by other.";
			}
			else
			{
				//new Email
				if (!is_null($phone_no))
				{
					//Phone number is there
					//to check if it's duplicate or not
					$query="SELECT * FROM users WHERE phone_no=?";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("s", $phone_no);
					$stmt->execute();
					$result=$stmt->get_result();
					$user=$result->fetch_assoc();
					
					if (!is_null($user))
					{
						//duplicate Phone number
						$response['status_code']=4;
						$response['message']="Duplicate Phone Number";
					}
					else
					{
						//new Phone number
						$created=date('Y-m-d H:i:s');
						$md5pass=md5($password);
							
						$query="INSERT INTO users (device_token, username, email, password, phone_no, profile, created, last_login) VALUES(?, ?, ?, ?, ?, ?, ?, ?)";
						$stmt=$this->db->prepare($query);
						$stmt->bind_param("ssssssss", $device_token, $username, $email, $md5pass, $phone_no, $profileurl, $created, $created);
						$stmt->execute();
						
						if ($stmt->affected_rows>=1)
						{
							$userid=$stmt->insert_id;
								
							$query="SELECT * FROM users WHERE id=?";
							$stmt=$this->db->prepare($query);
							$stmt->bind_param("i", $userid);
							$stmt->execute();
							$result=$stmt->get_result();
							$user=$result->fetch_assoc();
								
							$response['status_code']=1;
							$response['message']='success';
							$response['user']=$user;
							
							//Phone number is uploaded and add friends with the phone number
							$query="SELECT DISTINCT(userid) FROM friendslist WHERE frend_phone=?";
							$stmt=$this->db->prepare($query);
							$stmt->bind_param("s", $phone_no);
							$stmt->execute();
							$result=$stmt->get_result();
								
							$friends=array();
							while($each=$result->fetch_assoc())
							{
								$frendid=$each['userid'];
								
								$friends[]=$frendid;
								
								//no need to check if they are already friends, because phone number is unique
								$inquery="INSERT INTO friends (userid, friendid) VALUES(?, ?)";
								$instmt=$this->db->prepare($inquery);
								$instmt->bind_param("ii", $userid, $frendid);
								$instmt->execute();
							}
							
							foreach($friends as $friend)
							{
								//get friends of a friend
								$query="SELECT DISTINCT * FROM friends WHERE (userid<>? OR friendid<>?) AND (userid=? OR friendid=?)";
								$stmt=$this->db->prepare($query);
								$stmt->bind_param("iiii", $userid, $userid, $friend, $friend);
								$stmt->execute();
								$result=$stmt->get_result();
								
								while($friendlvl2=$result->fetch_assoc())
								{
									if ($friendlvl2['userid']==$userid)
									{
										$friendlvl2id=$friendlvl2['friendid'];
									}
									else
									{
										$friendlvl2id=$friendlvl2['userid'];
									}
									//check duplicacy
									$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
									$instmt=$this->db->prepare($inquery);
									$instmt->bind_param("iiii", $userid, $friendlvl2id, $friendlvl2id, $userid);
									$instmt->execute();
									$inresult=$instmt->get_result();
									$inrow=$inresult->fetch_assoc();
									
									if (!is_null($inrow))
									{
										//Already they are friends at leve 1. direct friends
										//Do nothing
									}
									else
									{
										//they are not friends, make them friends at level 2, indirect friend
										$insertquery="INSERT INTO friends (userid, friendid, frendlevel) VALUES(?, ?, 2)";
										$insertstmt=$this->db->prepare($insertquery);
										$insertstmt->bind_param("ii", $userid, $friendlvl2id);
										$insertstmt->execute();
									}
								}
								
							}
						}
						else
						{
							//Error in creating a user
							$response['status_code']=2;
							$response['message']="Error in creating a user";
						}
							
					}
				}
				else
				{
					//No phone number
					$created=date('Y-m-d H:i:s');
					$md5pass=md5($password);
					
					$query="INSERT INTO users (device_token, username, email, password, profile, created, last_login) VALUES(?, ?, ?, ?, ?, ?, ?)";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("sssssss", $device_token, $username, $email, $md5pass, $profileurl, $created, $created);
					$stmt->execute();
						
					if ($stmt->affected_rows>=1)
					{
						$userid=$stmt->insert_id;
						
						$query="SELECT * FROM users WHERE id=?";
						$stmt=$this->db->prepare($query);
						$stmt->bind_param("i", $userid);
						$stmt->execute();
						$result=$stmt->get_result();
						$user=$result->fetch_assoc();
						
						$response['status_code']=1;
						$response['message']='success';
						$response['user']=$user;
					}
					else
					{
						//Error in creating a user
						$response['status_code']=2;
						$response['message']="Error in creating a user";
					}
				}
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
	
		echo json_encode($response);
	}
	
	function update_profile()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
	
		$userid= isset($req_data["userid"]) ? $req_data["userid"] : null;
		
		//Image uploading
		if (!isset($_FILES['profile']) || !isset($_FILES['profile']['type']))
		{
			$response['status_code']=2;
			$response['message']="You should upload a profile picture.";
			echo json_encode($response);
			return;
		}
		
		$allowedExts = array("gif", "jpeg", "jpg", "png");
		$extension = "jpg";//end(explode(".", $_FILES["photo"]["name"]));
		
		if ((($_FILES['profile']["type"] == "image/gif")
				|| ($_FILES['profile']["type"] == "image/jpeg")
				|| ($_FILES['profile']["type"] == "image/jpg")
				|| ($_FILES['profile']["type"] == "image/pjpeg")
				|| ($_FILES['profile']["type"] == "image/x-png")
				|| ($_FILES['profile']["type"] == "image/png"))
				&& in_array($extension, $allowedExts))
		{
			if ($_FILES['profile']["error"] > 0)
			{
				$profileurl = null;
			}
			else
			{
				$timemd5=md5(microtime());
				$filename = "profile".$timemd5.".".$extension;
				$profileurl = "profiles/" .$filename;
				$copied=move_uploaded_file($_FILES['profile']["tmp_name"],	$profileurl);			
			}
		}
		else
		{
			$profileurl = null;
		}
			
		if (!is_null($userid))
		{
			$query="UPDATE users SET profile=? WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("si", $profileurl, $userid);
			$stmt->execute();
			
			$response['status_code']=1;
			$response['message']='success';
			$response['profile']=$profileurl;
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
	
		echo json_encode($response);
	}
	
	function add_phone_contacts()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		$contacts=isset($req_data['contacts']) ? $req_data['contacts'] : null;
		
		if (!is_null($userid) && !is_null($contacts))
		{
			//Add Phone Contacts
			$phonenums=explode(',', $contacts);
			foreach($phonenums as $phone)
			{
				$insertquery="INSERT INTO friendslist (userid, frend_phone) VALUES(?, ?)";
				$insertstmt=$this->db->prepare($insertquery);
				$insertstmt->bind_param("is", $userid, $phone);
				$insertstmt->execute();
			}
			
			$response['status_code']=1;
			$response['message']='success';
			
			//Add friends
			foreach($phonenums as $phone)
			{
				$query="SELECT * FROM users WHERE phone_no=? LIMIT 1";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("s", $phone);
				$stmt->execute();
				$result=$stmt->get_result();
				$user=$result->fetch_assoc();
				
				if (!is_null($user))
				{
					//Matching user
					
					$matchingid=$user['id'];
					//check if they are already friends or not
					$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("iiii", $userid, $matchingid, $matchingid, $userid);
					$instmt->execute();
					$inresult=$instmt->get_result();
					$inrow=$inresult->fetch_assoc();
					
					if (!is_null($inrow))
					{
						//They are already friends, no need to make it again
					}
					else
					{
						//They are not friends. Now they are friends
						$insertquery="INSERT INTO friends (userid, friendid, frendlevel) VALUES(?, ?, 1)";
						$insertstmt=$this->db->prepare($insertquery);
						$insertstmt->bind_param("ii", $userid, $matchingid);
						$insertstmt->execute();
						
						//Friends of mine are his friends, too.
						$inquery="SELECT * FROM friends WHERE (userid=? OR friendid=?) AND (userid<>? AND friendid<>?) AND (frendlevel=1)";
						$instmt=$this->db->prepare($inquery);
						$instmt->bind_param("iiii", $userid, $userid, $matchingid, $matchingid);
						$instmt->execute();
						$inresult=$instmt->get_result();
						
						while($row=$inresult->fetch_assoc())
						{
							if ($row['userid']==$userid)
							{
								$indirectid=$row['friendid'];
							}
							else
							{
								$indirectid=$row['userid'];
							}
							
							//check duplicacy
							$ckquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
							$ckstmt=$this->db->prepare($ckquery);
							$ckstmt->bind_param("iiii", $matchingid, $indirectid, $indirectid, $matchingid);
							$ckstmt->execute();
							$ckresult=$ckstmt->get_result();
							$ckrow=$ckresult->fetch_assoc();
							if (!is_null($ckrow))
							{
								//They are already indirect friends
							}
							else
							{
								//New indirect friends
								$insertquery="INSERT INTO friends (userid, friendid, frendlevel) VALUES(?, ?, 2)";
								$insertstmt=$this->db->prepare($insertquery);
								$insertstmt->bind_param("ii", $matchingid, $indirectid);
								$insertstmt->execute();
							}
						}
					}
				}
				else
				{
					//No matching user
				}
			}
			
			//Add or update my friends
			$query="SELECT * FROM friends WHERE (userid=? OR friendid=?) AND frendlevel=1";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $userid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			while($friend=$result->fetch_assoc())
			{
				if ($friend['userid']==$userid)
				{
					$friendid=$friend['friendid'];
				}
				else
				{
					$friendid=$friend['userid'];
				}
				
				//get friends of a friend
				$query="SELECT DISTINCT * FROM friends WHERE (userid<>? AND friendid<>?) AND (userid=? OR friendid=?) AND (frendlevel=1)";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("iiii", $userid, $userid, $friendid, $friendid);
				$stmt->execute();
				$result=$stmt->get_result();
					
				while($friendlvl2=$result->fetch_assoc())
				{
					if ($friendlvl2['userid']==$friendid)
					{
						$friendlvl2id=$friendlvl2['friendid'];
					}
					else
					{
						$friendlvl2id=$friendlvl2['userid'];
					}
					//check duplicacy
					$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("iiii", $userid, $friendlvl2id, $friendlvl2id, $userid);
					$instmt->execute();
					$inresult=$instmt->get_result();
					$inrow=$inresult->fetch_assoc();
			
					if (!is_null($inrow))
					{
						//Already they are friends at leve 1. direct friends
						//Do nothing
					}
					else
					{
						//they are not friends, make them friends at level 2, indirect friend
						$insertquery="INSERT INTO friends (userid, friendid, frendlevel) VALUES(?, ?, 2)";
						$insertstmt=$this->db->prepare($insertquery);
						$insertstmt->bind_param("ii", $userid, $friendlvl2id);
						$insertstmt->execute();
					}
				}
			}
				
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
		
	}
	
	function add_facebook_friends()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		$fbid=isset($req_data['fbid']) ? $req_data['fbid'] : null;
		$contacts=isset($req_data['contacts']) ? $req_data['contacts'] : null;
		
		if (!is_null($userid) && !is_null($contacts))
		{
			//Add Email List
			$emailids=explode(',', $contacts);
			foreach($emailids as $email)
			{
				$insertquery="INSERT INTO friendslist (userid, frend_email) VALUES(?, ?)";
				$insertstmt=$this->db->prepare($insertquery);
				$insertstmt->bind_param("is", $userid, $email);
				$insertstmt->execute();
			}
			
			//Add friends
			$query="SELECT * FROM users WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			$user=$result->fetch_assoc();
			
			$useremail=$user['email'];
			
			$query="SELECT DISTINCT(userid) FROM friendslist WHERE frend_email=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("s", $useremail);
			$stmt->execute();
			$result=$stmt->get_result();
			
			$friends=array();
			while($each=$result->fetch_assoc())
			{
				$frendid=$each['userid'];
				
				$friends[]=$friendid;
				
				$inquery="INSERT INTO friends (userid, friendid) VALUES(?, ?)";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("ii", $userid, $frendid);
				$instmt->execute();
			}
			
			foreach($friends as $friend)
			{
				//get friends of a friend
				$query="SELECT DISTINCT * FROM friends WHERE (userid<>? OR friendid<>?) AND (userid=? OR friendid=?)";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("iiii", $userid, $userid, $friend, $friend);
				$stmt->execute();
				$result=$stmt->get_result();
			
				while($friendlvl2=$result->fetch_assoc())
				{
					if ($friendlvl2['userid']==$userid)
					{
						$friendlvl2id=$friendlvl2['friendid'];
					}
					else
					{
						$friendlvl2id=$friendlvl2['userid'];
					}
					//check duplicacy
					$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("iiii", $userid, $friendlvl2id, $friendlvl2id, $userid);
					$instmt->execute();
					$inresult=$instmt->get_result();
					$inrow=$inresult->fetch_assoc();
						
					if (!is_null($inrow))
					{
						//Already they are friends at leve 1. direct friends
						//Do nothing
					}
					else
					{
						//they are not friends, make them friends at level 2, indirect friend
						$insertquery="INSERT INTO friends (userid, friendid, frendlevel) VALUES(?, ?, 2)";
						$insertstmt=$this->db->prepare($insertquery);
						$insertstmt->bind_param("ii", $userid, $friendlvl2id);
						$insertstmt->execute();
					}
				}
			
			}
			
			$response['status_code']=1;
			$response['message']='success';
			
			//Update user's fbid field
			$query="UPDATE users SET fbid=? WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("si", $fbid, $userid);
			$stmt->execute();
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function post_feed()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		$lat=isset($req_data['lat']) ? $req_data['lat'] : 0.0;
		$lng=isset($req_data['lng']) ? $req_data['lng'] : 0.0;
		$description=isset($req_data['description']) ? $req_data['description'] : null;
		$address=isset($req_data['address']) ? $req_data['address'] : '';
		
		if (!is_null($userid))
		{
			//Image uploading
			if (!isset($_FILES['photo']['type']))
			{
				$response['status_code']=2;
				$response['message']="You should upload a picture.";
				echo json_encode($response);
				return;
			}
			
			$allowedExts = array("gif", "jpeg", "jpg", "png");
			$extension = "jpg";//end(explode(".", $_FILES["photo"]["name"]));
			
			if ((($_FILES['photo']["type"] == "image/gif")
					|| ($_FILES['photo']["type"] == "image/jpeg")
					|| ($_FILES['photo']["type"] == "image/jpg")
					|| ($_FILES['photo']["type"] == "image/pjpeg")
					|| ($_FILES['photo']["type"] == "image/x-png")
					|| ($_FILES['photo']["type"] == "image/png"))
					&& in_array($extension, $allowedExts))
			{
				if ($_FILES['photo']["error"] > 0)
				{
					$imageurl = null;
				}
				else
				{
					$timemd5=md5(microtime());
					$filename = "feedimages".$timemd5.".".$extension;
					$imageurl = "uploads/" .$filename;
					$copied=move_uploaded_file($_FILES['photo']["tmp_name"],	$imageurl);			
				}
			}
			else
			{
				$imageurl = null;
			}
			//end of image uploading
			
			if (!is_null($imageurl))
			{
				$created=date('Y-m-d H:i:s');
				//Image Uploaded successfully
				$query="INSERT INTO posts(userid, image_url, caption, location, lat, lng, created) 
							VALUES(?, ?, ?, ?, ?, ?, ?)";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("issssss", $userid, $imageurl, $description, $address, $lat, $lng, $created);
				$stmt->execute();
				
				$newid=$stmt->insert_id;
				
				//If there's a description, it should be the first comment for the post.
				if (!is_null($description))
				{
					$query="INSERT INTO comments(postid, commentorid, comment, created) VALUES(?, ?, ?, ?)";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("iiss", $newid, $userid, $description, $created);
					$stmt->execute();
					
					$query="UPDATE posts SET commentscnt=commentscnt+1 WHERE id=?";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("i", $newid);
					$stmt->execute();
				}
				
				$response['status_code']=1;
				$response['message']='success';
				$response['postid']=$newid;
			}
			else
			{
				//fail to upload the image
				$response['status_code']=3;
				$response['message']='Image Uploading Failure';
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function load_feeds()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		
		if (!is_null($userid))
		{
			$query="SELECT * FROM friends WHERE userid=? OR friendid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $userid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			
			$ids='';
			$friends=array();
			while($row=$result->fetch_assoc())
			{
				if ($row['userid']==$userid)
				{
					$friendid=$row['friendid'];
				}
				else
				{
					$friendid=$row['userid'];
				}
				
				$friend['friendid']=$friendid;
				$friend['friendlevel']=$row['frendlevel'];
				
				$friends[]=$friend;
				
				$ids=$ids.$friendid.',';
			}
			
			if (!empty($ids))
			{
				//have Friends, load Feeds of friends and friends of friends
				//$idlist=substr($ids, 0, -1);
				
				//Add me as a friend
				$idlist=$ids.$userid;
				
			}
			else
			{
				//No Friends, Only My Feeds
				$idlist=$userid;
			}
			
			/*$searchquery="SELECT t2.*, users.profile, users.username FROM users 
				RIGHT JOIN (SELECT t1.*, likes.id as likeid FROM likes 
				RIGHT JOIN (SELECT * FROM posts WHERE userid IN (" . $idlist . "))  
				AS t1 ON t1.id=likes.postid AND likes.userid=? ORDER BY t1.created DESC) 
				AS t2 ON t2.userid=users.id";*/
			
			$searchquery="SELECT t2.*, users.profile, users.username FROM users 
				RIGHT JOIN (SELECT t1.*, likes.id as likeid FROM likes 
				RIGHT JOIN (SELECT * FROM posts WHERE DATE(created) >= DATE(CURDATE()))  
				AS t1 ON t1.id=likes.postid AND likes.userid=? ORDER BY t1.created DESC) 
				AS t2 ON t2.userid=users.id";
			
			$searchstmt=$this->db->prepare($searchquery);
			$searchstmt->bind_param("i", $userid);
			$searchstmt->execute();
			$sresult=$searchstmt->get_result();
			
			$current=date('Y-m-d H:i:s');
			$feeds=array();
			while($feed=$sresult->fetch_assoc())
			{
				$friendid=$feed['userid'];
				$feed['current']=$current;
				
				$postid=$feed['id'];
				
				$inquery="SELECT * FROM comments WHERE postid=? AND commentorid=? ORDER BY created DESC LIMIT 1";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("ii", $postid, $userid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
				$commentorid=null;
				$last_comment='';
				
				if (!is_null($inrow))
				{
					$commentorid=$inrow['id'];
					$last_comment=$inrow['comment'];
				}
				
				$feed['commentorid']=$commentorid;
				$feed['last_comment']=$last_comment;
				
				$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("iiii", $userid, $friendid, $friendid, $userid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$row=$inresult->fetch_assoc();
				
				if (!is_null($row))
				{
					//It should be for sure.
					$feed['frendlevel']=$row['frendlevel'];
				}
				else
				{
					//Just in case
					$feed['frendlevel']=1;
				}
				
				$inquery="SELECT * FROM users WHERE id=?";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("i", $friendid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$row=$inresult->fetch_assoc();
				
				if (!is_null($row))
				{
					$feed['device_token']=$row['device_token'];
				}
				else
				{
					//In case user is not existing, it should never be a case, but just in case
					$feed['device_token']='';
				}
				
				$feeds[]=$feed;
			}
			
			$response['status_code']=1;
			$response['message']='success';
			$response['posts']=$feeds;
			
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function comment_on_post()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
		
		$postid=isset($req_data['postid']) ? $req_data['postid'] : null;
		$commentorid=isset($req_data['commentorid']) ? $req_data['commentorid'] : null;
		$comment=isset($req_data['comment']) ? $req_data['comment'] : '';
		
		if (!is_null($postid) && !is_null($commentorid))
		{
			$created=date('Y-m-d H:i:s');
			$query="INSERT INTO comments (postid, commentorid, comment, created) VALUES(?, ?, ?, ?)";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("iiss", $postid, $commentorid, $comment, $created);
			$stmt->execute();
			
			$response['status_code']=1;
			$response['message']='success';
			
			//Update the count of comments of a post
			$query="UPDATE posts SET commentscnt=commentscnt+1 WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $postid);
			$stmt->execute();
			
			//Notifications, type=2
			//get the number of comments
			$query="SELECT * FROM posts WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $postid);
			$stmt->execute();
			$result=$stmt->get_result();
			$row=$result->fetch_assoc();
			
			$num=$row['commentscnt'];
			$userid=$row['userid'];
			
			$numofcomments=$row['commentscnt'];
			$numoflikes=$row['likescnt'];
			
			if ($userid!=$commentorid)
			{
				//send push notification
				$device_token = '';
				$username = '';
				
				$query="SELECT id, username, device_token FROM users WHERE id IN (?,?)";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("ii", $userid, $commentorid);
				$stmt->execute();
				$result=$stmt->get_result();
				
				$row=$result->fetch_assoc();
				if($row['id'] == $userid) {
					$device_token=$row['device_token'];
				}
				else {
					$username = $row['username'];
				}
				
				$row=$result->fetch_assoc();
				if($row['id'] == $commentorid) {
					$username = $row['username'];
				}
				else {
					$device_token=$row['device_token'];
				}
					
				$message = $username.' commented on your photo';
				$this->apns_push(array($device_token),false,$message);
				
				//check if it's already there or not
				$query="SELECT * FROM notifications WHERE userid=? AND postid=? AND type=2";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("ii", $commentorid, $postid);
				$stmt->execute();
				$result=$stmt->get_result();
				$row=$result->fetch_assoc();
					
				/*if (!is_null($row))
				{
					//notification already there
					$notificationid=$row['id'];
					//Update the count of likes of a post
					$query="UPDATE notifications SET message=?, num=?, created=?, isread=false WHERE id=?";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("sisi", $message, $num, $created, $notificationid);
					$stmt->execute();
				}
				else
				{*/
					//not there, need to create a new notification
					$query="INSERT INTO notifications (userid, postid, message, num, type, created) VALUES(?, ?, ?, ?, 2, ?)";
					$stmt=$this->db->prepare($query);
					$num = 1;
					$stmt->bind_param("iisis", $commentorid, $postid, $message, $num, $created);
					$stmt->execute();
				//}
			}
			
			//Notifications, type=4
			//Send notifications to all the users who commented or liked this post
			//commented first
			/*
			$query="SELECT * FROM comments WHERE postid=? AND commentorid<>?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $postid, $commentorid);
			$stmt->execute();
			$result=$stmt->get_result();
			
			while($row=$result->fetch_assoc())
			{
				$pushuserid=$row['commentorid'];
				
				//check if it's already there or not
				$inquery="SELECT * FROM notifications WHERE userid=? AND postid=? AND type=4";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("ii", $pushuserid, $postid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
					
				if (!is_null($inrow))
				{
					//notification already there
					$notificationid=$inrow['id'];
					//Update the count of likes of a post
					$inquery="UPDATE notifications SET num=?, created=?, isread=false WHERE id=?";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("isi", $numofcomments, $created, $notificationid);
					$instmt->execute();
				}
				else
				{
					//not there, need to create a new notification
					$inquery="INSERT INTO notifications (userid, postid, message, num, type, created) VALUES(?, ?, ?, ?, 4, ?)";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("iisis", $pushuserid, $postid, $message, $numofcomments, $created);
					$instmt->execute();
				}
			}
			
			//liked
			$query="SELECT * FROM likes WHERE postid=? AND userid<>?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $postid, $commentorid);
			$stmt->execute();
			$result=$stmt->get_result();
				
			while($row=$result->fetch_assoc())
			{
				$pushuserid=$row['userid'];
			
				//check if it's already there or not
				$inquery="SELECT * FROM notifications WHERE userid=? AND postid=? AND type=5";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("ii", $pushuserid, $postid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
					
				if (!is_null($inrow))
				{
					//notification already there
					$notificationid=$inrow['id'];
					//Update the count of likes of a post
					$inquery="UPDATE notifications SET num=?, created=?, isread=false WHERE id=?";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("isi", $numoflikes, $created, $notificationid);
					$instmt->execute();
				}
				else
				{
					//not there, need to create a new notification
					$inquery="INSERT INTO notifications (userid, postid, message, num, type, created) VALUES(?, ?, ?, ?, 5, ?)";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("iisis", $pushuserid, $postid, $message, $numoflikes, $created);
					$instmt->execute();
				}
			}
			*/
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
		
	}
	
	function load_comments()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		$postid=isset($req_data['postid']) ? $req_data['postid'] : null;
		
		if (!is_null($postid) && !is_null($userid))
		{
			$query="SELECT t1.*,users.profile,users.username FROM users 
				RIGHT JOIN(SELECT t.*, likecomments.id as likecommentid FROM likecomments 
				RIGHT JOIN(SELECT * FROM comments WHERE postid=?)
				AS t ON t.id=likecomments.commentid AND likecomments.userid=? ORDER BY t.created DESC)
				AS t1 ON t1.commentorid=users.id";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $postid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			
			$current=date('Y-m-d H:i:s');
			
			$comments=array();
			while($comment = $result->fetch_assoc())
			{
				$comment['current']=$current;
				
				$commentorid=$comment['commentorid'];
				$inquery="SELECT device_token FROM users WHERE id=?";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("i", $commentorid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
				
				$comment['device_token']=$inrow['device_token'];
				
				$comments[] = $comment;
			}
			
			$query="SELECT users.id, users.device_token FROM users WHERE id IN (SELECT userid FROM likes WHERE postid=?)";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $postid);
			$stmt->execute();
			$result=$stmt->get_result();
			
			$likes=array();
			while($liker=$result->fetch_assoc())
			{
				$likes[]=$liker;
			}
			
			$response['status_code']=1;
			$response['message']='success';
			$response['comments']=$comments;
			$response['likes']=$likes;
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function like_post()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$postid=isset($req_data['postid']) ? $req_data['postid'] : null;
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		
		if (!is_null($postid) && !is_null($userid))
		{
			$query="SELECT * FROM likes WHERE postid=? AND userid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $postid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			$like=$result->fetch_assoc();
			
			if (!is_null($like))
			{
				//already liked, remove like
				$likeid=$like['id'];
				$query="DELETE FROM likes WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $likeid);
				$stmt->execute();
				
				$query="UPDATE posts SET likescnt=likescnt-1 WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $postid);
				$stmt->execute();
				
				$response['status_code']=1;
				$response['message']="removed like";
			}
			else
			{
				//newly like
				$created=date('Y-m-d H:i:s');
				$query="INSERT INTO likes (postid, userid, created) VALUES(?, ?, ?)";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("iis", $postid, $userid, $created);
				$stmt->execute();
				
				$response['status_code']=1;
				$response['message']="success";
				
				//Update the count of likes of a post
				$query="UPDATE posts SET likescnt=likescnt+1 WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $postid);
				$stmt->execute();
				
				//Notifications, type=1
				//get the number of likes
				$query="SELECT * FROM posts WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $postid);
				$stmt->execute();
				$result=$stmt->get_result();
				$row=$result->fetch_assoc();
				
				$num=$row['likescnt'];
				$ownerid=$row['userid'];
				
				if ($ownerid!=$userid)
				{
					//send notifications only when others like my post
					$device_token = '';
					$username = '';
					
					$query="SELECT id, username, device_token FROM users WHERE id IN (?,?)";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("ii", $ownerid, $userid);
					$stmt->execute();
					$result=$stmt->get_result();
					
					$row=$result->fetch_assoc();
					if($row['id'] == $ownerid) {
						$device_token=$row['device_token'];
					}
					else {
						$username = $row['username'];
					}
					
					$row=$result->fetch_assoc();
					if($row['id'] == $userid) {
						$username = $row['username'];
					}
					else {
						$device_token=$row['device_token'];
					}
					
					$message = $username.' like your photo';
					$this->apns_push(array($device_token),false,$message);
					
					//check if it's already there or not
					$query="SELECT * FROM notifications WHERE userid=? AND postid=? AND num=1 AND type=1";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("ii", $userid, $postid);
					$stmt->execute();
					$result=$stmt->get_result();
					$row=$result->fetch_assoc();
					
					if (isset($row['id']))
					{
						//notification already there
						$notificationid=$row['id'];
						//Update the count of likes of a post, set isread to false
						$query="UPDATE notifications SET message=?, num=?, created=?, isread=false WHERE id=?";
						$stmt=$this->db->prepare($query);
						$stmt->bind_param("sisi", $message, $num, $created, $notificationid);
						$stmt->execute();
					}
					else
					{
						//not there, need to create a new notification
						$query="INSERT INTO notifications (userid, postid, message, num, type, created) VALUES(?, ?, ?, ?, 1, ?)";
						$stmt=$this->db->prepare($query);
						$stmt->bind_param("iisis", $userid, $postid, $message, $num, $created);
						$stmt->execute();
					}
				}				
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
		
	}
	
	function like_comment()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$commentid=isset($req_data['commentid']) ? $req_data['commentid'] : null;
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		
		if (!is_null($commentid) && !is_null($userid))
		{
			$query="SELECT * FROM likecomments WHERE commentid=? AND userid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $commentid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			$like=$result->fetch_assoc();
				
			if (!is_null($like))
			{
				//already liked, remove like
				$likeid=$like['id'];
				$query="DELETE FROM likecomments WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $likeid);
				$stmt->execute();
				
				$query="UPDATE comments SET likescnt=likescnt-1 WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $commentid);
				$stmt->execute();
				
				$response['status_code']=1;
				$response['message']="already liked, removed like";
			}
			else
			{
				//newly like
				$created=date('Y-m-d H:i:s');
				$query="INSERT INTO likecomments (commentid, userid, created) VALUES(?, ?, ?)";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("iis", $commentid, $userid, $created);
				$stmt->execute();
		
				$response['status_code']=1;
				$response['message']="success";
		
				//Update the count of likes of a post
				$query="UPDATE comments SET likescnt=likescnt+1 WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $commentid);
				$stmt->execute();
				
				//Notifications, type=3
				//get the number of likes
				$query="SELECT * FROM comments WHERE id=?";
				$stmt=$this->db->prepare($query);
				$stmt->bind_param("i", $commentid);
				$stmt->execute();
				$result=$stmt->get_result();
				$row=$result->fetch_assoc();
				
				$postid=$row['postid'];
				$num=$row['likescnt'];
				$commentorid=$row['commentorid'];
					
				if ($userid!=$commentorid)
				{
					//send push notification
					/*
					$query="SELECT device_token FROM users WHERE userid=?";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("i", $userid);
					$stmt->execute();
					$result=$stmt->get_result();
					$row=$result->fetch_assoc();
					$device_token=$row['device_token'];
					$this->apns_push(array($device_token),true,'Someone liked on your comment');
					*/
					
					//check if it's already there or not
					$query="SELECT * FROM notifications WHERE userid=? AND postid=? AND type=3";
					$stmt=$this->db->prepare($query);
					$stmt->bind_param("ii", $commentorid, $postid);
					$stmt->execute();
					$result=$stmt->get_result();
					$row=$result->fetch_assoc();
						
					if (!is_null($row))
					{
						//notification already there
						$notificationid=$row['id'];
						//Update the count of likes of a post
						$query="UPDATE notifications SET num=?, created=?, isread=false WHERE id=?";
						$stmt=$this->db->prepare($query);
						$stmt->bind_param("isi", $num, $created, $notificationid);
						$stmt->execute();
					}
					else
					{
						//not there, need to create a new notification
						$query="INSERT INTO notifications (userid, postid, num, type, created) VALUES(?, ?, ?, 3, ?)";
						$stmt=$this->db->prepare($query);
						$stmt->bind_param("iiis", $commentorid, $postid, $num, $created);
						$stmt->execute();
					}
				}
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function get_my_friends()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		
		if (!is_null($userid))
		{
			$query="SELECT COUNT(*) AS numberoffriends FROM friends WHERE userid=? OR friendid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $userid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			$row=$result->fetch_assoc();
			
			$response['status_code']=1;
			$response['message']='success';
			$response['numberoffriends']=$row['numberoffriends'];
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function get_a_feed()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$postid=isset($req_data['postid']) ? $req_data['postid'] : null;
		
		if (!is_null($postid))
		{
			$query="SELECT * FROM posts WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $postid);
			$stmt->execute();
			$result=$stmt->get_result();
			$row=$result->fetch_assoc();
			
			$current=date('Y-m-d H:i:s');
			$row['current']=$current;
				
			$response['status_code']=1;
			$response['message']='success';
			$response['post']=$row;
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function load_notifications()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		
		if (!is_null($userid))
		{
			$query="SELECT t1.*, t2.profile FROM notifications t1 LEFT JOIN users t2 ON t1.userid=t2.id WHERE userid=? ORDER BY created DESC";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $userid);
			$stmt->execute();
			$result=$stmt->get_result();
			
			$notifications=array();
			while($row=$result->fetch_assoc())
			{				
				$inquery="SELECT * FROM posts WHERE id=?";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("i", $row['postid']);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
				
				$row['image_url']=$inrow['image_url'];
				$notifications[]=$row;
			}
			
			$response['status_code']=1;
			$response['message']='success';
			$response['notifications']=$notifications;
			
			//set isread=true since all the notifications are loaded
			/*$query="UPDATE notifications SET isread=true WHERE userid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $userid);
			$stmt->execute();*/
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
		
	}
	
	function read_notification()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$notificationid=isset($req_data['notificationid']) ? $req_data['notificationid'] : null;
		
		if (!is_null($notificationid))
		{
			$query="UPDATE notifications SET isread=true WHERE id=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("i", $notificationid);
			$stmt->execute();
			
			$response['status_code']=1;
			$response['message']='success';
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function read_notifications()
	{
		$req_data = $_POST;
		$response = array();
		$response["status_code"] = 0;
		
		$notificationids=isset($req_data['notificationids']) ? $req_data['notificationids'] : null;
		
		if (!is_null($notificationids))
		{
			$query="UPDATE notifications SET isread=true WHERE id IN (".$notificationids.")";
			$stmt=$this->db->prepare($query);
			//$stmt->bind_param("s", $notificationids);
			$stmt->execute();
			
			$response['status_code']=1;
			$response['message']='success';
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function load_feeds_nearby()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
	
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		$lat=isset($req_data['lat']) ? $req_data['lat'] : null;
		$lng=isset($req_data['lng']) ? $req_data['lng'] : null;
		$radius=isset($req_data['radius']) ? $req_data['radius'] : 500;
	
		if (!is_null($userid) && !is_null($lat) && !is_null($lng))
		{
			$searchquery="SELECT t2.*, users.profile, users.username FROM users 
				RIGHT JOIN (SELECT t1.*, likes.id as likeid FROM likes
				RIGHT JOIN (SELECT posts.*, (3959 * acos(cos(radians(?))*cos(radians(posts.lat))*cos(radians(posts.lng)-radians(?))+sin(radians(?))*sin(radians(posts.lat)))) as distance FROM posts HAVING distance<?)
				AS t1 ON t1.id=likes.postid AND likes.userid=? ORDER BY t1.created DESC) 
				AS t2 ON t2.userid=users.id";
			$searchstmt=$this->db->prepare($searchquery);
			$searchstmt->bind_param("ssssi", $lat, $lng, $lat, $radius, $userid);
			$searchstmt->execute();
			$sresult=$searchstmt->get_result();
			
			$current=date('Y-m-d H:i:s');
			$feeds=array();
			while($feed=$sresult->fetch_assoc())
			{
				$friendid=$feed['userid'];
				$feed['current']=$current;
			
				$postid=$feed['id'];
			
				$inquery="SELECT * FROM comments WHERE postid=? AND commentorid=? ORDER BY created DESC LIMIT 1";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("ii", $postid, $userid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
				
				$commentorid=null;
				$last_comment='';
				
				if (!is_null($inrow))
				{
					$commentorid=$inrow['id'];
					$last_comment=$inrow['comment'];
				}
			
				$feed['commentorid']=$commentorid;
				$feed['last_comment']=$last_comment;
				
				if ($userid==$friendid)
				{
					$feed['frendlevel']=1;
				}
				else
				{
					$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
					$instmt=$this->db->prepare($inquery);
					$instmt->bind_param("iiii", $userid, $friendid, $friendid, $userid);
					$instmt->execute();
					$inresult=$instmt->get_result();
					$row=$inresult->fetch_assoc();
						
					if (!is_null($row))
					{
						//They are friends or at least friends of friends
						$feed['frendlevel']=$row['frendlevel'];
					}
					else
					{
						//They have no relation
						$feed['frendlevel']=0;
					}
				}
			
				$inquery="SELECT * FROM users WHERE id=?";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("i", $friendid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$row=$inresult->fetch_assoc();
			
				if (!is_null($row))
				{
					$feed['device_token']=$row['device_token'];
				}
				else
				{
					//In case user is not existing, it should never be a case, but just in case
					$feed['device_token']='';
				}
			
				$feeds[]=$feed;
			}
				
			$response['status_code']=1;
			$response['message']='success';
			$response['posts']=$feeds;
				
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
	
		echo json_encode($response);
	}
	
	function load_my_friends()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
		
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		
		if (!is_null($userid))
		{
			$query="SELECT * FROM friends WHERE userid=? OR friendid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $userid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
				
			$ids='';
			while($row=$result->fetch_assoc())
			{
				if ($row['userid']==$userid)
				{
					$friendid=$row['friendid'];
				}
				else
				{
					$friendid=$row['userid'];
				}
							
				$ids=$ids.$friendid.',';
			}
			
			$friends=array();
			
			if (!empty($ids))
			{
				//have Friends
				$idlist=substr($ids, 0, -1);
				
				$query="SELECT users.id, users.device_token FROM users WHERE id IN (".$idlist.")";
				$stmt=$this->db->prepare($query);
				$stmt->execute();
				$result=$stmt->get_result();
					
				$friends=array();
				while($friend=$result->fetch_assoc())
				{
					$friends[]=$friend;
				}
					
				$response['status_code']=1;
				$response['message']='success';
				$response['friends']=$friends;
			}
			else
			{
				//no friends
				$response['status_code']=1;
				$response['message']='success';
				$response['friends']=$friends;
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
		
		echo json_encode($response);
	}
	
	function load_feeds_with_keyword()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
	
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
		$keyword=isset($req_data['keyword']) ? $req_data['keyword'] : null;
	
		if (!is_null($userid) && !is_null($keyword))
		{
			$query="SELECT * FROM friends WHERE userid=? OR friendid=?";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $userid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
				
			$ids='';
			$friends=array();
			while($row=$result->fetch_assoc())
			{
				if ($row['userid']==$userid)
				{
					$friendid=$row['friendid'];
				}
				else
				{
					$friendid=$row['userid'];
				}
	
				$friend['friendid']=$friendid;
				$friend['friendlevel']=$row['frendlevel'];
	
				$friends[]=$friend;
	
				$ids=$ids.$friendid.',';
			}
				
			if (!empty($ids))
			{
				//have Friends, load Feeds of friends and friends of friends
				//$idlist=substr($ids, 0, -1);
	
				//Add me as a friend
				$idlist=$ids.$userid;
	
			}
			else
			{
				//No Friends, Only My Feeds
				$idlist=$userid;
			}
				
			$searchquery="SELECT t1.*, likes.id as likeid FROM likes
				RIGHT JOIN(SELECT * FROM posts WHERE userid IN (" . $idlist . ") AND location like '%" . $keyword . "%')
				AS t1 ON t1.id=likes.postid AND likes.userid=? ORDER BY t1.created DESC";
			$searchstmt=$this->db->prepare($searchquery);
			$searchstmt->bind_param("i", $userid);
			$searchstmt->execute();
			$sresult=$searchstmt->get_result();
				
			$current=date('Y-m-d H:i:s');
			$feeds=array();
			while($feed=$sresult->fetch_assoc())
			{
				$friendid=$feed['userid'];
				$feed['current']=$current;
	
				$postid=$feed['id'];
	
				$inquery="SELECT * FROM comments WHERE postid=? AND commentorid=? LIMIT 1";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("ii", $postid, $userid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$inrow=$inresult->fetch_assoc();
	
				$commentorid=null;
				if (!is_null($inrow))
				{
					$commentorid=$inrow['id'];
				}
	
				$feed['commentorid']=$commentorid;
	
				$inquery="SELECT * FROM friends WHERE (userid=? AND friendid=?) OR (userid=? AND friendid=?)";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("iiii", $userid, $friendid, $friendid, $userid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$row=$inresult->fetch_assoc();
	
				if (!is_null($row))
				{
					//It should be for sure.
					$feed['frendlevel']=$row['frendlevel'];
				}
				else
				{
					//Just in case
					$feed['frendlevel']=1;
				}
	
				$inquery="SELECT * FROM users WHERE id=?";
				$instmt=$this->db->prepare($inquery);
				$instmt->bind_param("i", $friendid);
				$instmt->execute();
				$inresult=$instmt->get_result();
				$row=$inresult->fetch_assoc();
	
				if (!is_null($row))
				{
					$feed['device_token']=$row['device_token'];
				}
				else
				{
					//In case user is not existing, it should never be a case, but just in case
					$feed['device_token']='';
				}
	
				$feeds[]=$feed;
			}
				
			$response['status_code']=1;
			$response['message']='success';
			$response['posts']=$feeds;
				
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
	
		echo json_encode($response);
	}
	
	function load_my_friends_phone()
	{
		$req_data=$_POST;
		$response=array();
		$response['status_code']=0;
	
		$userid=isset($req_data['userid']) ? $req_data['userid'] : null;
	
		if (!is_null($userid))
		{
			$query="SELECT * FROM friends WHERE frendlevel=1 AND (userid=? OR friendid=?)";
			$stmt=$this->db->prepare($query);
			$stmt->bind_param("ii", $userid, $userid);
			$stmt->execute();
			$result=$stmt->get_result();
	
			$ids='';
			while($row=$result->fetch_assoc())
			{
				if ($row['userid']==$userid)
				{
					$friendid=$row['friendid'];
				}
				else
				{
					$friendid=$row['userid'];
				}
					
				$ids=$ids.$friendid.',';
			}
				
			$friends=array();
				
			if (!empty($ids))
			{
				//have Friends
				$idlist=substr($ids, 0, -1);
	
				$query="SELECT users.* FROM users WHERE id IN (".$idlist.")";
				$stmt=$this->db->prepare($query);
				$stmt->execute();
				$result=$stmt->get_result();
					
				$friends=array();
				while($friend=$result->fetch_assoc())
				{
					$friends[]=$friend;
				}
					
				$response['status_code']=1;
				$response['message']='success';
				$response['friends']=$friends;
			}
			else
			{
				//no friends
				$response['status_code']=1;
				$response['message']='success';
				$response['friends']=$friends;
			}
		}
		else
		{
			$response["status_code"]=0;
			$response["message"]="parameters not set";
		}
	
		echo json_encode($response);
	}
	
	function apns_push($tokens=array(),$development=true,$message='',$custom_data=array(), $badge=1,$sound='default'){

		//$device_token='4aabc9b8c96e06623e1053ee0745df02a0303f3892c0b0c20fb406ae39e9e7ce';
		$payload = array();
		$payload['aps'] = array('alert' => $message, 'badge' => intval($badge), 'sound' => $sound);
		
		$payload['custom'] = $custom_data;
		$payload = json_encode($payload);
		
		$apns_url = NULL;
		$apns_cert = NULL;
		$apns_port = 2195;
		$pass = '123';
		
		if($development)
		{
			$apns_url = 'gateway.sandbox.push.apple.com';
			//$apns_cert = 'apns-dev-cert.pem';
			$apns_cert = '/var/www/html/apns-dev-cert.pem';
		}
		else
		{
			$apns_url = 'gateway.push.apple.com';
			//$apns_cert = 'apns-adhoc-cert.pem';
			$apns_cert = '/var/www/html/apns-adhoc-cert.pem';
		}
		
		$stream_context = stream_context_create();
		stream_context_set_option($stream_context, 'ssl', 'local_cert', $apns_cert);
		stream_context_set_option($stream_context, 'ssl', 'passphrase', $pass);
		
		$apns = stream_socket_client('ssl://' . $apns_url . ':' . $apns_port, $error, $error_string, 300, STREAM_CLIENT_CONNECT, $stream_context);
		
		if (!$apns) {
			//print "Failed to connect $error $error_string";
			//$response["status_code"]=0;
			//$response["message"]="Failed to send push notification $error $error_string";
			//echo json_encode($response); exit;
			return;
		} else {
			//print "Connection OK\n";
		}
		
		//	You will need to put your device tokens into the $device_tokens array yourself
		if(!empty($tokens)){
			foreach($tokens as $device_token)
			{
				$apns_message = chr(0) . chr(0) . chr(32) . pack('H*', str_replace(' ', '', $device_token)) . chr(0) . chr(strlen($payload)) . $payload;
				fwrite($apns, $apns_message);
			}
		}
		//@socket_close($apns);
		@fclose($apns);
	}
}

date_default_timezone_set('UTC');

$pook=new pookAPI;
$service=isset($_POST['service']) ? $_POST['service'] : null;

$get=isset($_GET['get']) ? $_GET['get'] : null;

if (!is_null($service))
{
	if ($service=="test")
	{
		$pook->test();
	}
	if ($service=="pushtest")
	{
		$pook->push_test();
	}
	else if ($service=="signup")
	{
		$pook->signup();
	}
	else if ($service=="signin")
	{
		$pook->signin();
	}
	else if ($service=="updateprofile")
	{
		$pook->update_profile();
	}
	else if ($service=="addphonecontacts")
	{
		$pook->add_phone_contacts();
	}
	else if ($service=="addfacebookfriends")
	{
		$pook->add_facebook_friends();
	}
	else if ($service=="postfeed")
	{
		$pook->post_feed();
	}
	else if ($service=="loadfeeds")
	{
		$pook->load_feeds();
	}
	else if ($service=="commentonpost")
	{
		$pook->comment_on_post();
	}
	else if ($service=="loadcomments")
	{
		$pook->load_comments();
	}
	else if ($service=="likepost")
	{
		$pook->like_post();
	}
	else if ($service=="likecomment")
	{
		$pook->like_comment();
	}
	else if ($service=="getmyfriends")
	{
		$pook->get_my_friends();
	}
	else if ($service=="getafeed")
	{
		$pook->get_a_feed();
	}
	else if ($service=="loadnotifications")
	{
		$pook->load_notifications();
	}
	else if ($service=="readnotification")
	{
		$pook->read_notification();
	}
	else if ($service=="readnotifications")
	{
		$pook->read_notifications();
	}
	else if ($service=="loadfeedsnearby")
	{
		$pook->load_feeds_nearby();
	}
	else if ($service=="loadmyfriends")
	{
		$pook->load_my_friends();
	}
	else if ($service=="loadfeedswithkeyword")
	{
		$pook->load_feeds_with_keyword();
	}
	else if ($service=="loadmyfriendsphone")
	{
		$pook->load_my_friends_phone();
	}
}
elseif (!is_null($get)) {
	if ($get=="info")
	{
		$pook->test();
	}
}
else
{
	$pook->index();
	phpinfo();
}

?>
