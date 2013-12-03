<?php
$query = 'volkskrant.nl/';

// passwords, keys, db-settings
require_once('settings.local.php');
// Create our twitter API object
require_once("twitteroauth.php");
// functions, which contains teh gzdecode alternative
require_once('functions.php');
// html-dom parser, but it sucks too, lot's of preg_match_all
include_once ('simple_html_dom.php');

// database, mysql, why not?
include('db.php');

$months = array('januari' => 1 ,'februari' => 2, 'maart' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'augustus' => 8, 'september' => 9, 'oktober' => 10,'november' => 11,'december' => 12);


$since = get_since();

echo "\n".strftime('%Y-%m-%d %H:%M').' sinds: '.$since."\n";
// go to https://dev.twitter.com/apps and create new application
// and obtain [CONSUMER_KEY], [CONSUMER_SECRET], [oauth_key], [oauth_secret]
// then put them in settings.local.php
$oauth = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, OAUTH_KEY, OAUTH_SECRET);

// Make up a useragent
$oauth->useragent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/13.6.0.9';

$tweets_found = json_decode(
                  $oauth->get( 'http://api.twitter.com/1.1/search/tweets.json',
                                array('q' => $query,
                                      'count' => 100,
                                      'since_id' => $since,
                                      'result_type' => 'recent')
                              )
                            );
if(is_object($tweets_found)) foreach ($tweets_found->statuses as $tweet){
	//print_r($tweet->entities->urls);
	update_since($tweet->id);

	foreach($tweet->entities->urls as $url)
	{
		$tco = $url->url;

		$share = $url->expanded_url;
		if(! strstr($share, 'volkskrant'))
		{
			$short = $share;
			$short_res = mysql_query('select * from unshorten where short_url = "'.$short.'"');
			if(mysql_num_rows($short_res) == 0)
			{
				echo $short."\n";
				$share = unshorten_url($short);
				echo 'became-> '.$share."\n\n";
				// opslaan opdat we deze niet nogmaals opvragen
				mysql_query('insert into unshorten (short_url, url) values ("'.addslashes($short).'","'.addslashes($share).'")');
			}
			else
			{ // misschien moeten we hem tellen?
				$short_arr = mysql_fetch_array($short_res);
				$share = $short_arr['url'];
			}
		}
		if(strstr($share, 'volkskrant'))
		{
			$parsed = parse_url ($share);
			if (isset($parsed['path']))
			{
				if (! strstr($parsed['host'], 'volkskrant.nl') )
				{
					echo 'skipping: '.$share."\n";
					continue;
				}
				$path = $parsed['path'];
				$path = explode('/', $path);
				if(isset($path[2]) && isset($path[3]))
				{
					$path = $path[1].'/'.$path[2];
					$clean = $parsed['scheme'].'://'.$parsed['host'].$parsed['path'];
					$query = 'select * from artikelen where clean_url = "'.$clean.'"';
					$res = mysql_query($query);
					if(mysql_num_rows($res))
					{
						if (COUNT_TWEETS == 1)
						{
							$art_row = mysql_fetch_array($res);
							$tweet_res = mysql_query('select * from tweets where art_id = '.$art_row['ID'].' and tweet_id = "'.$tweet->id.'"');
							if (mysql_num_rows($tweet_res) == 0)
							{
								echo 'counting tweet '.$tweet->id."\n";
								mysql_query('insert into tweets (tweet_id, art_id) values ("'.$tweet->id.'", '.$art_row['ID'].')');
							}
						}

						continue; // hebben we al!
					}
					// even de url opvragen om de auteur te vinden
					$html = file_get_html($share);
					$og = array();
					if (is_object($html))
					{
						foreach( $html->find('meta[property^=og:], meta[name^=twitter:], meta[property^=twitter:]') as $meta )
						{
							if(strstr($meta->property, 'og:'))
							{
								$key = substr($meta->property,3);
								$og[$key] = stripslashes($meta->content);
							}
						}
						$tit_arr = $html->find('title');
						foreach($tit_arr as $tit)
						{
							$full_title = $tit->plaintext;
						}

						$sectie_arr = explode(' - ', $full_title);
						if (! empty($sectie_arr[1]))
						{
							$og['article:section'] = trim($sectie_arr[1]);
							echo "\nSectie: {$sectie_arr[1]}\n";
						}
						else print_r($sectie_arr);

						$auteurs = $html->find('.author');
						$author = '';
						foreach($auteurs as $auteur)
						{
							$author = trim(str_replace('Door:', '', str_replace('&copy;', '', $auteur->plaintext)));
							$author = str_replace('Bewerkt door:', '', $author);
							echo $author."\n";
						}
						if (empty($author)) {
							$auteurs = $html->find('.credit');
							foreach($auteurs as $auteur)
							{
								$author = trim(str_replace('&copy;', '', $auteur->plaintext));
							}
						}

						$og['article:author'] = $author;
						$times = $html->find('.time_post');
						foreach($times as $time)
						{
							$pubtime = explode('&minus',$time->plaintext);
							$pubtime = explode(' ', $pubtime[1]);
							$pubtime = $pubtime[1];
							echo 'pubtime -> '.$pubtime;
							echo '->'.strtotime($pubtime)."\n";

						}
					}

					// nu mogen we serializen
					$og = serialize($og);

					echo 'inserting: insert into artikelen (t_co, clean_url, share_url, og) values ("'.$tco.'", "'.$clean.'", "'.$share.'", "'.substr($og,0,20).'")'."\n";
					mysql_query('insert into artikelen (t_co, clean_url, share_url, og) values ("'.$tco.'", "'.$clean.'", "'.$share.'", "'.addslashes($og).'")');

					if (COUNT_TWEETS == 1)
					{
						echo 'counting tweet '.$tweet->id."\n";
						mysql_query('insert into tweets (tweet_id, art_id) values ("'.$tweet->id.'", '.mysql_insert_id().')');
					}

				}
			}
		}
	}
}
echo strftime('%Y-%m-%d %H:%M').' Batch ended, bye'."\n";

// alle meta-waardes wegschrijven in de meta-table voor makkelijker cross-linken:
// selecteer alle artikelen die geen meta_artikel rows bezitten
$res = mysql_query ('select artikelen.ID as art_id, og from artikelen left outer join meta_artikel on artikelen.ID = meta_artikel.art_id where meta_artikel.art_id IS NULL');
$skip_keys = array('url', 'locale', 'site_name');
while ($row = mysql_fetch_array($res))
{
	$og = unserialize($row['og']);
	$art_id = $row['art_id'];
	foreach($og as $key => $value)
	{
		if(in_array($key, $skip_keys))
			continue;

		$meta_res = mysql_query('select * from meta where `type` = "'.$key.'" and waarde = "'.$value.'"');
		if (mysql_num_rows($meta_res) == 0)
		{
			mysql_query('insert into meta (waarde, type) values ("'.$value.'", "'.$key.'")');
			$meta_id = mysql_insert_id();
		}
		else
		{
			$meta_arr = mysql_fetch_array($meta_res);
			$meta_id = $meta_arr['ID'];
		}
		// koppel aan het gevonden artikel
		$link_res = mysql_query('select * from meta_artikel where art_id = '.$art_id.' and meta_id = '.$meta_id);
		if( mysql_num_rows($link_res) == 0)
		{ // en maak de meta-link
			mysql_query('insert into meta_artikel (art_id, meta_id) values ('.$art_id.', '.$meta_id.')');
		}
	}
}

function update_since($since)
{
	$query = 'update app_keys set app_keys.app_value = "'.$since.'" where app_key = "since"';
	mysql_query($query);
}

function get_since()
{
	$res = mysql_query('select app_value from app_keys where app_key = "since"');
	$row = mysql_fetch_array($res);
	return $row['app_value'];
}


function unshorten_url($url)
{
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
                                CURLOPT_FOLLOWLOCATION => TRUE,  // the magic sauce
                                CURLOPT_RETURNTRANSFER => TRUE,
                                CURLOPT_SSL_VERIFYHOST => FALSE, // suppress certain SSL errors
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                               )
                    );
	curl_exec($ch);
	$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	return $url;
}
