<?php
	////////////
	// COMMON //
	////////////

	// Validate every call to api
	function validateAccess(){
		foreach (getallheaders() as $name => $value) {
			if ($name == 'Authorization') {
				$validate = checkUserAuthorization($value);
			}
		}
	};

	// Call all time to validate
	validateAccess();

	// Check if session with authorization token exists
	function checkUserAuthorization($data){
		global $conn;

		if ($data == '0xQ3s1RVrSRpWtcN') {
			$result = true;
		} else {
			$data = explode('xQ3s1RVrSR', $data);
			$user = $data[0];
			$authentication = $data[1];

			$sql = "SELECT id
					FROM z_logins
					WHERE user = $user 
						AND authorization = '$authentication'";
			$result = $conn->query($sql);

			// Close all ACCESS
			if ($result->num_rows == 0){
				$conn->close();
				exit;
			}
		}
	}

	// Get languages
	function getLanguages(){
		global $conn;

		$sql = "SELECT id, caption
				FROM z_languages
				WHERE is_deleted = 0
				ORDER BY caption ASC";
		$result = $conn->query($sql);

		$data = array();
		while($row = $result->fetch_assoc()) {
			$data[] = $row;
		}

		return $data;
	}

	// Time format for videos
	function timeFormat($timeInSeconds) {
		$timeInSeconds = round($timeInSeconds);
		$timeInSeconds = abs($timeInSeconds);
		$hours = floor($timeInSeconds / 3600) . ':';
		$minutes = floor(($timeInSeconds / 60) % 60). ':';
		$seconds = substr('00' . $timeInSeconds % 60, -2);

		if ($hours == '0:')
			$hours = '';

		return $hours.$minutes.$seconds;
	}

	// Generate random string
	function generateRandomString($length) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';

		for ($i = 0; $i < $length; $i++)
			$randomString .= $characters[rand(0, $charactersLength - 1)];

		return $randomString;
	}

	//////////
	// USER //
	//////////

	// Get user id by username
	function userLoginActivity($user){
		global $conn;
		$ipAddress = $_SERVER['REMOTE_ADDR'];

		// Browser
		$browser = $_SERVER['HTTP_USER_AGENT'];

		// Authorization
		$authorization = generateRandomString(23*3);

		$sql = "INSERT INTO z_logins (user, authorization, browser, ip_address)
				VALUES ($user, '$authorization', '$browser', '$ipAddress')";
		$result = $conn->query($sql);

		return $authorization;
	}

	// Get user id by username
	function userId($username){
		global $conn;

		$sql = "SELECT id
				FROM z_users
				WHERE username LIKE '$username'";
		$result = $conn->query($sql)->fetch_assoc();

		if ($result['id'])
			$result = $result['id'];
		else
			$result = "";

		return $result;
	}

	// Get user name by id
	function userName($id){
		global $conn;

		$sql = "SELECT name
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		return html_entity_decode($result['name'], ENT_QUOTES);
	}

	// Get user username by id
	function userUsername($id){
		global $conn;

		$sql = "SELECT username
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		return $result['username'];
	}

	// Get user avatar by id
	function userAvatar($id){
		global $conn;

		$sql = "SELECT avatar
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		return $result['avatar'];
	}

	// Get user avatar by id
	function userPassword($id){
		global $conn;

		$sql = "SELECT password
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		return $result['password'];
	}

	// Get user data by id
	function userData($id){
		global $conn;

		$sql = "SELECT id, username, name, avatar, background, email, about, about_original as aboutOriginal, language, theme, official, private
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql);

		$data = array();
		while($row = $result->fetch_assoc()) {
			$row['avatarUrl'] = $row['avatar'] ? ('./assets/media/user/'.$row['id'].'/avatar/'.$row['avatar']) : '';
			$row['backgroundUrl'] = $row['background'] ? ('./assets/media/user/'.$row['id'].'/background/'.$row['background']) : '';
			$row['languages'] = getLanguages();
			$row['theme'] = $row['theme'] ? true : false;
			$row['official'] = $row['official'] ? true : false;
			$row['private'] = $row['private'] ? true : false;
			$row['name'] = html_entity_decode($row['name'], ENT_QUOTES);
			$row['about'] = html_entity_decode($row['about'], ENT_QUOTES);
			$row['aboutOriginal'] = html_entity_decode($row['aboutOriginal'], ENT_QUOTES);
			$row['countFollowing'] = countUserFollowing($id);
			$row['countFollowers'] = countUserFollowers($id);
			$row['countPhotos'] = countUserPhotos($id);
			$row['countAudios'] = countUserAudios($id);
			$row['countBookmarks'] = countUserBookmarks($id);
			$data = $row;
		}

		return $data;
	}

	// Get user triplete data by id
	function userUsernameNameAvatar($id){
		global $conn;

		$sql = "SELECT id, username, name, avatar, official 
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['name'] = html_entity_decode($result['name'], ENT_QUOTES);
		$result['avatarUrl'] = $result['avatar'] ? ('./assets/media/user/'.$result['id'].'/avatar/'.$result['avatar']) : '';

		return $result;
	}

	// Get user private
	function checkUserPrivacy($id){
		global $conn;

		$sql = "SELECT private
				FROM z_users
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['private'] = $result['private'] ? true : false;

		return $result['private'];
	}

	// Check if I'm following my follower
	function checkFollowingStatus($sender, $receiver){
		global $conn;

		$sql = "SELECT id, status
				FROM z_following
				WHERE sender = $sender 
					AND receiver = $receiver 
					AND is_deleted = 0";
		$result = $conn->query($sql)->fetch_assoc();

		if ($result)
			$result = ($result['status'] == 1) ? 'pending' : 'following';
		else
			$result = 'unfollow';

		return $result;
	}

	// Count following
	function countUserFollowing($id){
		global $conn;

		$sql = "SELECT id
				FROM z_following
				WHERE sender = $id 
					AND is_deleted = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Count followers
	function countUserFollowers($id){
		global $conn;

		$sql = "SELECT id
				FROM z_following
				WHERE receiver = $id 
					AND is_deleted = 0 
					AND status = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Count photos
	function countUserPhotos($id){
		global $conn;

		$sql = "SELECT id
				FROM z_photos_favorites
				WHERE user = $id 
					AND is_deleted = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Count audios
	function countUserAudios($id){
		global $conn;

		$sql = "SELECT id
				FROM z_audios_favorites
				WHERE user = $id 
					AND is_deleted = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Count bookmarks
	function countUserBookmarks($id){
		global $conn;

		$sql = "SELECT b.id
				FROM z_bookmarks b
					INNER JOIN z_publications p ON p.id = b.post 
				WHERE b.user = $id 
					AND b.is_deleted = 0
					AND p.is_deleted = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	////////////
	// AUDIOS //
	////////////

	// Get audio data
	function getAudioData($id){
		global $conn;

		$sql = "SELECT id, name, title, original_title, original_artist, image, duration
				FROM z_audios
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['song'] = $result['id'];
		$result['title'] = html_entity_decode($result['title'], ENT_QUOTES);
		$result['original_title'] = html_entity_decode($result['original_title'], ENT_QUOTES);
		$result['original_artist'] = html_entity_decode($result['original_artist'], ENT_QUOTES);

		return $result;
	}

	// Get inserted playlist
	function getPlaylist($id){
		global $conn;

		$sql = "SELECT id, title, image, private
				FROM z_audios_playlist
				WHERE id = $id";
		$result = $conn->query($sql);

		$data = array();
		while($row = $result->fetch_assoc()) {
			$row['title'] = html_entity_decode($row['title'], ENT_QUOTES);
			$row['private'] = $row['private'] ? true : false;
			$row['idPlaylist'] = $row['id'];
			$data = $row;
		}

		return $data;
	}

	// Get playlists
	function getPlaylists($user){
		global $conn;

		$sql = "SELECT id, title, private
				FROM z_audios_playlist
				WHERE user = $user 
					AND is_deleted = 0
				ORDER BY date DESC";
		$result = $conn->query($sql);

		$data = array();
		while($row = $result->fetch_assoc()) {
			$row['private'] = $row['private'] ? true : false;
			$row['idPlaylist'] = $row['id'];
			$data[] = $row;
		}

		return $data;
	}

	////////////
	// PHOTOS //
	////////////

	// Get photo data
	function getPhotoData($id){
		global $conn;

		$sql = "SELECT id, name, mimetype, duration
				FROM z_photos
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		return $result;
	}

	// Check if I liked photo
	function checkLikedPhoto($photo, $user){
		global $conn;

		$sql = "SELECT id
				FROM z_photos_likes
				WHERE photo = '$photo' 
					AND user = $user";
		$result = $conn->query($sql);

		if ($result->num_rows)
			$result = true;
		else
			$result = false;

		return $result;
	}

	// Get inserted comment in photo
	function getPhotoComment($id){
		global $conn;

		$sql = "SELECT id, user, photo, comment, date
				FROM z_photos_comments
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['user'] = userUsernameNameAvatar($result['user']);
		$result['comment'] = trim($result['comment']) ? html_entity_decode($result['comment'], ENT_QUOTES) : null;

		return $result;
	}

	// Get photo likers
	function getPhotoLikers($id){
		global $conn;

		$sql = "SELECT user
				FROM z_photos_likes
				WHERE photo = $id
				ORDER BY RAND()
				LIMIT 2";
		$result = $conn->query($sql);

		$data = array();
		while($row = $result->fetch_assoc()) {
			$row['id'] = $row['user'];
			$row['username'] = userUsername($row['user']);
			$data[] = $row;
		}

		return $data;
	}

	// Count likes photo
	function countLikesPhoto($id){
		global $conn;

		$sql = "SELECT id
				FROM z_photos_likes
				WHERE photo = $id";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Count comments photo
	function countCommentsPhoto($id){
		global $conn;

		$sql = "SELECT id
				FROM z_photos_comments
				WHERE photo = $id 
					AND is_deleted = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Get photo data for notification
	function getIdNameContentMediaCommentFromPhotoById($id, $commentId){
		global $conn;

		$sql = "SELECT p.id, p.name, p.mimetype, p.duration
				FROM z_photos_favorites f
					INNER JOIN z_photos p ON p.id = f.photo
				WHERE f.id = '$id'";
		$result = $conn->query($sql)->fetch_assoc();

		$result['comment'] = ($commentId ? getPhotoComment($commentId) : null);

		// Media
		if(strrpos($result['mimetype'], "image") !== false)
			$result['media'] = ($result['name'] ? './assets/media/photos/thumbnails/'.$result['name'] : null);
		else if(strrpos($result['mimetype'], "video") !== false)
			$result['media'] = ($result['name'] ? './assets/media/videos/thumbnails/'.$result['name'] : null);
		else
			$result['media'] = null;

		return $result;
	}

	//////////////////
	// PUBLICATIONS //
	//////////////////

	// Get publication
	function getPublication($id){
		global $conn;

		$sql = "SELECT id, user, name, content, url_video as urlVideo, photos, audios, disabled_comments as disabledComments, date
				FROM z_publications
				WHERE id = $id 
					AND is_deleted = 0";
		$result = $conn->query($sql)->fetch_assoc();

		if ($result) {
			$result['user'] = userUsernameNameAvatar($result['user']);
			$result['content'] = html_entity_decode($result['content'], ENT_QUOTES);
			$result['bookmark'] = array('id' => null, 'checked' => false);
			$result['likers'] = getPublicationLikers($result['id']);
			$result['disabledComments'] = ($result['disabledComments'] == 0) ? true : false;
			$result['countComments'] = countCommentsPublication($result['id']);
			$result['countLikes'] = countLikesPublication($result['id']);
			$result['comments'] = [];

			// Format urlVideo
			$result['urlVideo'] = json_decode($result['urlVideo']);
			if (count($result['urlVideo']) > 0) {
				$result['urlVideo']->title = html_entity_decode($result['urlVideo']->title, ENT_QUOTES);
				$result['urlVideo']->channel = html_entity_decode($result['urlVideo']->channel, ENT_QUOTES);
				$result['urlVideo']->iframe = html_entity_decode($result['urlVideo']->iframe, ENT_QUOTES);
			} else {
				$result['urlVideo'] = null;
			}

			// Format photos
			$result['photos'] = json_decode($result['photos']);
			foreach ($result['photos'] as &$p) {
				$p = getPhotoData($p);
			}

			// Format audios
			$result['audios'] = json_decode($result['audios']);
			foreach ($result['audios'] as &$a) {
				$a = getAudioData($a);
			}
		} else {
			$result = null;
		}

		return $result;
	}

	// Count likes publication
	function countLikesPublication($id){
		global $conn;

		$sql = "SELECT id
				FROM z_publications_likes
				WHERE publication = $id";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Count comments publication
	function countCommentsPublication($id){
		global $conn;

		$sql = "SELECT id
				FROM z_publications_comments
				WHERE publication = $id 
					AND is_deleted = 0";
		$result = $conn->query($sql);

		return $result->num_rows;
	}

	// Check if I liked a publication
	function checkLikedPublication($publication, $user){
		global $conn;

		$sql = "SELECT id
				FROM z_publications_likes
				WHERE publication = '$publication' 
					AND user = $user";
		$result = $conn->query($sql);

		if ($result->num_rows)
			$result = true;
		else
			$result = false;

		return $result;
	}

	// Check if I marked a publication
	function checkMarkedPublication($publication, $user){
		global $conn;

		$sql = "SELECT id
				FROM z_bookmarks
				WHERE post = $publication 
					AND user = $user
					AND is_deleted = 0";
		$result = $conn->query($sql)->fetch_assoc();

		$result['id'] = $result['id'] ? $result['id'] : null;
		$result['checked'] = $result['id'] ? true : false;

		return $result;
	}

	// Get inserted comment in publication
	function getPublicationComment($id){
		global $conn;

		$sql = "SELECT id, user, publication, comment, date
				FROM z_publications_comments
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['user'] = userUsernameNameAvatar($result['user']);
		$result['comment'] = trim($result['comment']) ? html_entity_decode($result['comment'], ENT_QUOTES) : null;

		return $result;
	}

	// Get inserted comment in publication
	function getPublicationLikers($id){
		global $conn;

		$sql = "SELECT user
				FROM z_publications_likes
				WHERE publication = $id
				ORDER BY RAND()
				LIMIT 2";
		$result = $conn->query($sql);

		$data = array();
		while($row = $result->fetch_assoc()) {
			$row['id'] = $row['user'];
			$row['username'] = userUsername($row['user']);
			$data[] = $row;
		}

		return $data;
	}

	// Upload audio files on publications
	function uploadAudiosPublication($user, $name, $mimetype, $title, $original_title, $original_artist, $genre, $image, $duration){
		global $conn;
		$ipAddress = $_SERVER['REMOTE_ADDR'];

		$sql = "INSERT INTO z_audios (user, name, mimetype, title, original_title, original_artist, genre, image, duration, ip_address)
				VALUES ($user, '$name', '$mimetype', '$title', '$original_title', '$original_artist', '$genre', '$image', '$duration', '$ipAddress')";
		$result = $conn->query($sql);
		$insertedId = $conn->insert_id;

		return $insertedId;
	}

	// Upload photo & video files on publications
	function uploadPhotosPublication($user, $name, $mimetype, $duration){
		global $conn;
		$ipAddress = $_SERVER['REMOTE_ADDR'];

		$sql = "INSERT INTO z_photos (user, name, mimetype, duration, ip_address)
				VALUES ($user, '$name', '$mimetype', '$duration', '$ipAddress')";
		$result = $conn->query($sql);
		$insertedId = $conn->insert_id;

		return $insertedId;
	}

	// Get publication data for notification
	function getIdNameContentMediaCommentFromPublicationById($id, $commentId){
		global $conn;

		$sql = "SELECT id, name, content, url_video as urlVideo, photos 
				FROM z_publications
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['content'] = html_entity_decode($result['content'], ENT_QUOTES);
		$result['comment'] = ($commentId ? getPublicationComment($commentId) : null);

		// Media
		$result['urlVideo'] = json_decode($result['urlVideo']);
		$result['photos'] = json_decode($result['photos']);
		
		if (count($result['photos']) > 0) {
			$result['media'] = getPhotoData($result['photos'][0]);

			if(strrpos($result['media']['mimetype'], "image") !== false)
				$result['media'] = ($result['media']['name'] ? './assets/media/photos/thumbnails/'.$result['media']['name'] : null);
			else if(strrpos($result['media']['mimetype'], "video") !== false)
				$result['media'] = ($result['media']['name'] ? './assets/media/videos/thumbnails/'.$result['media']['name'] : null);
			else
				$result['media'] = null;
		} else {
			if (count($result['urlVideo']) > 0)
				$result['media'] = $result['urlVideo']->thumbnail;
			else
				$result['media'] = null;
		}

		return $result;
	}

	// Get hashtag count
	function hashtagCount($caption){
		global $conn;

		$sql = "SELECT id
				FROM z_publications
				WHERE hashtags LIKE '%$caption%' 
					AND (
							(length(photos) > 0 AND is_deleted = 0) OR  
							(length(url_video) > 0 AND is_deleted = 0) OR 
							((length(photos) > 0 AND length(url_video) > 0) AND is_deleted = 0)
						)
				ORDER BY id";
		$result = $conn->query($sql)->num_rows;

		return $result;
	}

	function searchPublicationAnalytics($user, $caption, $type){
		global $conn;
		$ipAddress = $_SERVER['REMOTE_ADDR'];

		$sql = "INSERT INTO z_publications_search (user, caption, type, ip_address)
				VALUES ($user, '$caption', '$type', '$ipAddress')";
		$result = $conn->query($sql);
	}

	///////////////////
	// NOTIFICATIONS //
	///////////////////

	// Update notification status
	function updateNotificationStatus($id){
		global $conn;
		$ipAddress = $_SERVER['REMOTE_ADDR'];

		$sql = "UPDATE z_notifications
				SET status = '1', ip_address = '$ipAddress'
				WHERE id = $id";
		$result = $conn->query($sql);

		return true;
	}

	// Insert notification
	function generateNotification($data){
		global $conn;
		$ipAddress = $_SERVER['REMOTE_ADDR'];

		$id = $data['id'];
		$url = $data['url'];
		$type = $data['type'];
		$sender = $data['sender'];
		$receiver = $data['receiver'];
		$comment = $data['comment'];

		switch ($url) {
			case 'followers':
				if ($type == 'startFollowing') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_url = '$url'
								AND is_deleted = 0";
					$result = $conn->query($sql);

					$sqlInsert = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, ip_address)
									VALUES ($sender, $receiver, $id, '$url', '$type', '$ipAddress')";
					$resultInsert = $conn->query($sqlInsert);
				} else if ($type == 'stopFollowing') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_url = '$url'
								AND is_deleted = 0";
					$result = $conn->query($sql);
				} else if ($type == 'startFollowingPrivate') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_url = '$url' 
								AND page_type = 'acceptRequest'
								AND is_deleted = 0";
					$result = $conn->query($sql);

					$sql = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, ip_address)
							VALUES ($sender, $receiver, $id, '$url', '$type', '$ipAddress')";
					$result = $conn->query($sql);
				} else if ($type == 'acceptRequest') {
					$sql = "UPDATE z_notifications
							SET page_type = 'startFollowingPrivateAccepted', ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_url = '$url' 
								AND is_deleted = 0";
					$result = $conn->query($sql);

					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $receiver 
								AND receiver = $sender 
								AND page_url = '$url' 
								AND is_deleted = 0";
					$result = $conn->query($sql);

					$sqlInsert = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, ip_address)
									VALUES ($receiver, $sender, $id, '$url', '$type', '$ipAddress')";
					$resultInsert = $conn->query($sqlInsert);
				} else if ($type == 'declineRequest') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_url = '$url' 
								AND page_type = 'startFollowingPrivate'
								AND is_deleted = 0";
					$result = $conn->query($sql);
				}
				break;
			case 'photos':
			case 'publications':
				if ($type == 'like') {
					$sql = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, ip_address) 
							VALUES ($sender, $receiver, $id, '$url', '$type', '$ipAddress')";
					$result = $conn->query($sql);
				} else if ($type == 'unlike') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_id = $id 
								AND page_url = '$url' 
								AND page_type = 'like'";
					$result = $conn->query($sql);
				} else if ($type == 'comment') {
					$sql = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, comment_id, ip_address) 
							VALUES ($sender, $receiver, $id, '$url', '$type', $comment, '$ipAddress')";
					$result = $conn->query($sql);
				} else if ($type == 'uncomment') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 1, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_id = $id 
								AND page_url = '$url' 
								AND comment_id = $comment";
					$result = $conn->query($sql);
				} else if ($type == 'commentUncommented') {
					$sql = "UPDATE z_notifications
							SET is_deleted = 0, ip_address = '$ipAddress' 
							WHERE sender = $sender 
								AND receiver = $receiver 
								AND page_id = $id 
								AND page_url = '$url' 
								AND comment_id = $comment";
					$result = $conn->query($sql);
				} else if ($type == 'mention') {
					$sql = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, ip_address) 
							VALUES ($sender, $receiver, $id, '$url', '$type', '$ipAddress')";
					$result = $conn->query($sql);
				} else if ($type == 'mentionComment') {
					$sql = "INSERT INTO z_notifications (sender, receiver, page_id, page_url, page_type, comment_id, ip_address) 
							VALUES ($sender, $receiver, $id, '$url', '$type', $comment, '$ipAddress')";
					$result = $conn->query($sql);
				}
				break;
		}
	}

	//////////
	// CHAT //
	//////////

	// Get conversation users
	function getChatConversationUsers($id, $user){
		global $conn;

		$sql = "SELECT user
				FROM z_chat_users
				WHERE chat = $id 
					AND is_deleted = 0
				ORDER BY date DESC
				LIMIT 100";
		$result = $conn->query($sql);

		$dataAll = array();
		$dataExcluded = array();
		while($row = $result->fetch_assoc()) {
			$row['user'] = userUsernameNameAvatar($row['user']);
			$dataAll[] = $row;

			if ($user != $row['user']['id'])
				$dataExcluded[] = $row;
		}

		$data = array(
			"all" 	=> $dataAll,
			"excluded" 	=> $dataExcluded
		);

		return $data;
	}

	// Get last inserted comment
	function getChatConversationLastComment($id){
		global $conn;

		$sql = "SELECT content, date, type
				FROM z_chat_conversation
				WHERE chat = $id 
					AND is_deleted = 0
				ORDER BY date DESC
				LIMIT 1";
		$result = $conn->query($sql)->fetch_assoc();
		$result['content'] = trim($result['content']) ? html_entity_decode($result['content'], ENT_QUOTES) : null;

		return $result;
	}

	// Get inserted comment in conversation
	function getChatConversationComment($id){
		global $conn;

		$sql = "SELECT id, user, type, content, publication, date
				FROM z_chat_conversation
				WHERE id = $id";
		$result = $conn->query($sql)->fetch_assoc();

		$result['user'] = userUsernameNameAvatar($result['user']);
		$result['content'] = trim($result['content']) ? html_entity_decode($result['content'], ENT_QUOTES) : null;

		return $result;
	}
?>
