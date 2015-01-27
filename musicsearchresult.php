<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>#nowplaying</title>
</head>
<body>
<p>Twitterの#nowplayingでつぶやかれた回数順で表示します。</p>
<form method="POST" action="<?php print($_SERVER['PHP_SELF']) ?>">
アーティスト名：
<input type="text" name="artist_name">
<input type="submit" name="button1" value="検索"><br>
</form>
<a href="/nowplaying.php">戻る</a>
<?php

ini_set('display_errors','1');
error_reporting(E_ALL);

require_once('./twitteroauth.php');
require('./keys.php');

$consumerKey = CONSUMER_KEY;
$consumerSecret = CONSUMER_SECRET;
$accessToken = OAUTH_TOKEN;
$accessTokenSecret = OAUTH_TOKEN_SECRET;

if ($_POST['artist_name'] === "") {
	$artist_name = "名無し";
	print '<h3>アーティスト名を入力してください。</h3>';
} else {
	$artist_name = $_POST['artist_name'];
	print '<h3>'.$artist_name.' の検索結果</h3>';
}
$artist_name = str_replace(" ", "%20", $artist_name);
$url = "https://itunes.apple.com/search?country=JP&attribute=artistTerm&limit=200&lang=ja_jp&entity=song&term=".$artist_name;
$json = file_get_contents($url);
$songs = json_decode($json, true);
$songlist = array();
$albumlist = array();
foreach ($songs["results"] as $song) {
	$songlist[$song["trackName"]] = array(
		"trackName"=>$song["trackName"],
		"artistName"=>$song["artistName"],
		"collectionName"=>$song["collectionName"],
		"artworkUrl100"=>$song["artworkUrl100"],
		"previewUrl"=>$song["previewUrl"],
		"count"=>0
	);
	$albumlist[] = $song["collectionName"];
}
$twObj = new TwitterOAuth($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
$params = array(
    'lang'  => 'ja',
    'q'     => '%23nowplaying '.$artist_name,
    'count' => 200
);
$req = $twObj->get('search/tweets.json', $params);
$tweets = json_decode($req);
$maxid = songcount($tweets, $songlist, $albumlist, $artist_name);
for ($i = 0; $i < 8 and $maxid != -1; $i++) {
    $params = array('lang' => 'ja',
    'q'                    => '%23nowplaying '.$artist_name,
    'count'                => 100,
    'max_id'               => $maxid
    );
	$req = $twObj->get('search/tweets.json', $params);
	$tweets = json_decode($req);
	$maxid = songcount($tweets, $songlist, $albumlist, $artist_name);
}

function songcount($tweets, &$songlist, $albumlist, $artist_name) {
$maxid = -1;
$id = 0;
if (isset($tweets) && empty($tweets->errors)) {	
	$tweets = $tweets->statuses;
	foreach ($tweets as $val) {
		$tweet = $val->text;
		$id += 1;
		$maxid = $val->id;
		$song = array();
		foreach (array_keys($songlist) as $trackName) {
			if (strstr($tweet, $trackName)) {
					$song[] = $trackName;
			}
		}
		if (count($song) != 0) {
			$trackName = select_song($song, $albumlist, $artist_name);
			if ($trackName != NULL) {
				$songlist[$trackName]["count"] += 1;
			}
		}
	}
}
return $maxid;
}

function select_song($song, $albumlist, $artist_name) {
	if (in_array($artist_name, $song)) {
		for ($i = 0; $i < count($song); $i++) {
			if ($song[$i] === $artist_name) {	
				unset($song[$i]);
				$song = array_values($song);
				break;
			}
		}	
	}
	$song2 = $song;
	if (count($song2) > 1) {
		for ($i = 0; $i < count($song); $i++) {
			for ($j = 0; $j < count($song); $j++) {
				if ($i == $j) {
					continue;
				}
				if (strstr($song2[$i], $song2[$j])) {
					if ($song[$j] != NULL) {	
						unset($song[$j]);
					}
					break;
				}
			}
		}
	}
	$song = array_values($song);

	if (count($song) > 1) {	
		usort($song, create_function('$a,$b','return mb_strlen($b, "UTF-8") - mb_strlen($a, "UTF-8");'));
	}
	if (count($song) == 0) {
		return NULL;
	} else {
		return $song[0];
	}
}

foreach ($songlist as $song=>$songinfo) {
	$key_id[$song] = $songinfo["count"];
}
if (count($songlist) != 0) {
	array_multisort ($key_id, SORT_DESC, $songlist);
}
print "<table border=\"1\">";
foreach ($songlist as $song=>$songinfo) {
	print "<tr>";
	print "<td><img src=\"".$songinfo["artworkUrl100"]."\" /></td>";
	print "<td><audio src=\"".$songinfo["previewUrl"]."\" controls></td>";
	#print "<td><a href=\"".$songinfo["previewUrl"]."\"><img src=\"./preview.png\" /></a></td>";
	print "<td>".$songinfo["trackName"]."</td>";
	print "<td>".$songinfo["artistName"]."</td>";
	print "<td>".$songinfo["collectionName"]."</td>";
	print "<td>".$songinfo["count"]."</td>";
	print "</tr>";
}
print "</table>";
?>
<a href="/nowplaying.php">戻る</a>
</body>
</html>
