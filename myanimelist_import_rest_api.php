<?php
/*
Plugin Name: myanimelist_import_rest_api
Description: Importer By Agilesolution
Version: 1.2
Author: agilesolution
Author URI: http://agilesolution.co/
License: MIT
*/
require_once ('vendor/autoload.php');
require_once ('MAL_Scrapper/vendor/autoload.php');
require_once 'simple_html_dom.php';
use \aalfiann\MyAnimeList;
use MalScraper\MalScraper;

add_action('admin_menu', 'rest_api_setting');

function rest_api_setting() {

	add_menu_page('Rest API Settings', 'MyAnimeList Setting', 'administrator', 'setting.php', 'my_cool_plugin_settings_page' );
    add_submenu_page(  'setting.php', 'Import Anime List', 'Anime Series', 'administrator', 'anime_series.php', 'import_anime_series' );
	add_submenu_page(  'setting.php', 'Import Episodes List', 'Episodes', 'administrator', 'episodes.php', 'import_episodes' );
	
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'anime_api', '/search_anime/', array(
		'methods'  => 'GET',
		'callback' => 'search_anime',
	) );
	register_rest_route( 'anime_api', '/post_anime/', array(
		'methods'  => 'GET',
		'callback' => 'post_anime',
	) );
	
	
	
	register_rest_route( 'anime_api', '/post_episodes/', array(
		'methods'  => 'GET',
		'callback' => 'post_episodes',
	) );
} );

function Generate_Featured_Image( $image_url, $post_id  ){
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    if(wp_mkdir_p($upload_dir['path']))
      $file = $upload_dir['path'] . '/' . $filename;
    else
      $file = $upload_dir['basedir'] . '/' . $filename;
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
    $res2= set_post_thumbnail( $post_id, $attach_id );
}

function post_exists_by_title( $title, $content = '', $date = '', $type = '', $status = '' ) {
    global $wpdb;
 
    $post_title   = wp_unslash( sanitize_post_field( 'post_title', $title, 0, 'db' ) );
    $post_content = wp_unslash( sanitize_post_field( 'post_content', $content, 0, 'db' ) );
    $post_date    = wp_unslash( sanitize_post_field( 'post_date', $date, 0, 'db' ) );
    $post_type    = wp_unslash( sanitize_post_field( 'post_type', $type, 0, 'db' ) );
    $post_status  = wp_unslash( sanitize_post_field( 'post_status', $status, 0, 'db' ) );
 
    $query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
	
    $args  = array();
 
    if ( ! empty( $date ) ) {
        $query .= ' AND post_date = %s';
        $args[] = $post_date;
    }
 
    if ( ! empty( $title ) ) {
        $query .= ' AND post_title = %s';
        $args[] = $post_title;
    }
 
    if ( ! empty( $content ) ) {
        $query .= ' AND post_content = %s';
        $args[] = $post_content;
    }
 
    if ( ! empty( $type ) ) {
        $query .= ' AND post_type = %s';
        $args[] = $post_type;
    }
 
    if ( ! empty( $status ) ) {
        $query .= ' AND post_status = %s';
        $args[] = $post_status;
    }
 
    if ( ! empty( $args ) ) {
        return (int) $wpdb->get_var( $wpdb->prepare( $query, $args ) );
    }
 
    return 0;
}

function importing_with_episodes($anime_id,$total_episode,$episode_type){
	
	$myMalScraper = new MalScraper([
		'enable_cache' => false,
		'cache_time'   => 3600,
		'to_api'       => true,
	]);
	
	$method = 'episode';
	
	$url = "https://api.eastheme.com/myanimelist/anime/?id=".$anime_id;
	$anime_info = json_decode(file_get_contents($url),true);
	
	if($anime_info['english']){
		$fetch_title = $anime_info['english'];
	}else{
		$fetch_title = $anime_info['title'];
	}
	$catterm_exist = term_exists($fetch_title,'category')['term_id'];
	if($catterm_exist>0){
		$catgory_id = (int)$catterm_exist;
	}else{
		$catterm_response = wp_insert_term(
			$fetch_title,   // the term 
			'category', // the taxonomy
			array(
				'description' => $fetch_title,
				'slug'        => $fetch_title
			)
		);
		$catgory_id = (int)$catterm_response['term_id'];
	}
	
	// Call the requested method
	switch ($method) {
		case 'episode':
			if ($anime_id) {
				if($total_episode>0){
					$total_episode = $total_episode;
				}else{
					$total_episode = 50;
				}
				
				for($i=0;$i<$total_episode;$i++){
					$episode_no = $i+1;
					
					
						$gogoanime_suburl = $fetch_title." Episode ".$episode_no;
					    $gogoanime_suburl = preg_replace('/[^A-Za-z0-9 ]/', '', $gogoanime_suburl);
						$gogoanime_suburl = str_replace(" ", "-", $gogoanime_suburl);
						$domsub = file_get_html('https://gogoanime.wiki/'.$gogoanime_suburl, false);
						$contentsub = $domsub->find('iframe');
						$gogoanime_iframe_suburl = $contentsub[0]->src;
						//$goganime_type = substr($gogoanime_iframe_suburl, strrpos($gogoanime_iframe_suburl, '=') + 1);
						$episode_subtitle = $fetch_title." Episode ".$episode_no." English SUB"; 
						
						$gogoanime_duburl = $fetch_title." Dub Episode ".$episode_no;
					    $gogoanime_duburl = preg_replace('/[^A-Za-z0-9 ]/', '', $gogoanime_duburl);
						$gogoanime_duburl = str_replace(" ", "-", $gogoanime_duburl);
						$domdub = file_get_html('https://gogoanime.wiki/'.$gogoanime_duburl, false);
						$contentdub = $domdub->find('iframe');
						$gogoanime_iframe_duburl = $contentdub[0]->src;
						//$goganime_type = substr($gogoanime_iframe_duburl, strrpos($gogoanime_iframe_duburl, '=') + 1);
						$episode_dubtitle = $fetch_title." Episode ".$episode_no." English DUB"; 
						$found_post = post_exists_by_title($episode_dubtitle,'','','');
						$wp_anime_id = post_exists_by_title( $fetch_title,'','','anime');
					
					
					
					if($episode_type ==='sub'){
						if($found_post>0){
							$my_post = array(
								'ID'			  => $found_post,
								'post_title'    => $episode_subtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_update_post( $my_post );

							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'SUB');
							//echo "updated post id ".$postid;
						}else{
							$my_post = array(
								'post_title'    => $episode_subtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_insert_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'SUB');
							//echo "Inserted post id ".$postid;
						}
						
					}
					else if($episode_type=='dub'){
						if($found_post>0){
							$my_post = array(
								'ID'			  => $found_post,
								'post_title'    => $episode_dubtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_update_post( $my_post );

							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'DUB');
							//echo "updated post id ".$postid;
						}else{
							$my_post = array(
								'post_title'    => $episode_dubtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_insert_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'DUB');
							//echo "Inserted post id ".$postid;
						}
						
					}else{
						
						// In case both import For SUB
						if($found_post>0){
							$my_post = array(
								'ID'			  => $found_post,
								'post_title'    => $episode_subtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_update_post( $my_post );

							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'SUB');
							//echo "updated post id ".$postid;
							
							$my_post = array(
								'ID'			  => $found_post,
								'post_title'    => $episode_dubtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_update_post( $my_post );

							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'DUB');
							//echo "updated post id ".$postid;
							
						}else{
							$my_post = array(
								'post_title'    => $episode_subtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_insert_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'SUB');
							//echo "Inserted post id ".$postid;
							
							
							$my_post = array(
								'post_title'    => $episode_dubtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_insert_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'DUB');
							//echo "Inserted post id ".$postid;
						}
						
					}
					
				}
			} else {
				print_r(paramError());
			}
			break;
		default:
			print_r(paramError(true));
			break;
	}

	// Return error parameter
	function paramError($a = false)
	{
		$result = [];
		if ($a) {
			header('HTTP/1.1 404');
			$result['status'] = 404;
			$result['status_message'] = 'Method not found';
			$result['data'] = [];
		} else {
			header('HTTP/1.1 400');
			$result['status'] = 400;
			$result['status_message'] = 'Bad Request';
			$result['data'] = [];
		}

		return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

	
	
}

function post_episodes( object $args ){
	$myMalScraper = new MalScraper([
		'enable_cache' => false,
		'cache_time'   => 3600,
		'to_api'       => true,
	]);
	
	$method = isset($args['method']) ? $args['method'] : '';
// 	$anime_title = isset($args['anime_title']) ? $args['anime_title'] : '';
	$anime_id = isset($args['anime_id']) ? $args['anime_id'] : '';
	$episode_type = isset($args['episode_type']) ? $args['episode_type'] : '';
	
	$url = "https://api.eastheme.com/myanimelist/anime/?id=".$anime_id;
	$anime_info = json_decode(file_get_contents($url),true);
	if($anime_info['episodes']>0){
		$total_episode = $anime_info['episodes'];
	}else{
		$total_episode = 10;
	}
	
	
	if($anime_info['english']){
		$anime_title = $anime_info['english'];
	}else{
		$anime_title = $anime_info['title'];
	}
	$catterm_exist = term_exists($anime_title,'category')['term_id'];
	if($catterm_exist>0){
		$catgory_id = (int)$catterm_exist;
	}else{
		$catterm_response = wp_insert_term(
			$anime_title,   // the term 
			'category', // the taxonomy
			array(
				'description' => $anime_title,
				'slug'        => $anime_title
			)
		);
		$catgory_id = (int)$catterm_response['term_id'];
	}
	
	// Call the requested method
	switch ($method) {
		case 'video':
			if ($anime_id) {
				$result = $myMalScraper->getVideo($anime_id, 1);
				print_r($result);
			} else {
				print_r(paramError());
			}
			break;
		case 'episode':
			if ($anime_id) {
// 				$result = $myMalScraper->getEpisode($anime_id, 1);
// 				$res = json_decode($result,true)['data'];
				for($i=0;$i<$total_episode;$i++){
					$episode_no = $i+1;
					
					
					$gogoanime_suburl = $anime_title." Episode ".$episode_no;
					$gogoanime_suburl = preg_replace('/[^A-Za-z0-9 ]/', '', $gogoanime_suburl);
					$gogoanime_suburl = str_replace(" ", "-", $gogoanime_suburl);
					
					$domsub = file_get_html('https://gogoanime.wiki/'.$gogoanime_suburl, false);
					$contentsub = $domsub->find('iframe');
					$gogoanime_iframe_suburl = $contentsub[0]->src;
					
// 					$goganime_type = substr($gogoanime_iframe_url, strrpos($gogoanime_iframe_url, '=') + 1);
					$episode_subtitle = $anime_title." Episode ".$episode_no." English Subbed"; 
					
					
					$gogoanime_duburl = $anime_title." Dub Episode ".$episode_no;
					$gogoanime_duburl = preg_replace('/[^A-Za-z0-9 ]/', '', $gogoanime_duburl);
					$gogoanime_duburl = str_replace(" ", "-", $gogoanime_duburl);
					$domdub = file_get_html('https://gogoanime.wiki/'.$gogoanime_duburl, false);
					$contentdub = $domdub->find('iframe');
					$gogoanime_iframe_duburl = $contentdub[0]->src;
// 					$goganime_type = substr($gogoanime_iframe_url, strrpos($gogoanime_iframe_url, '=') + 1);
					$episode_dubtitle = $anime_title." Episode ".$episode_no." English Dubbed"; 
					$found_post = post_exists_by_title($episode_dubtitle,'','','');
					$wp_anime_id = post_exists_by_title( $anime_title,'','','anime');
					
					
					
					if($episode_type ==='sub'){
							if($found_post>0){
								$my_post = array(
								  'ID'			  => $found_post,
								  'post_title'    => $episode_subtitle,
								  'post_status'   => 'publish',
								  'post_category' => array( $catgory_id ),
								  'post_author'   => 1,
								);

								// Insert the post into the database
								$postid = wp_update_post( $my_post );

								$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

								update_post_meta($postid,'east_episode',$episode_no);
								update_post_meta($postid,'east_series',$wp_anime_id);
								update_post_meta($postid,'east_player',unserialize($east_player));
								update_post_meta($postid,'east_typesbdb', 'SUB');
								echo "updated post id ".$postid;
							}else{
								$my_post = array(
								  'post_title'    => $episode_subtitle,
								  'post_status'   => 'publish',
								  'post_category' => array( $catgory_id ),
								  'post_author'   => 1,
								);

								// Insert the post into the database
								$postid = wp_insert_post( $my_post );
								$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

								update_post_meta($postid,'east_episode',$episode_no);
								update_post_meta($postid,'east_series',$wp_anime_id);
								update_post_meta($postid,'east_player',unserialize($east_player));
								update_post_meta($postid,'east_typesbdb', 'SUB');
								echo "Inserted post id ".$postid;
							}
					}
					else if($episode_type=='dub'){
							if($found_post>0){
								$my_post = array(
								  'ID'			  => $found_post,
								  'post_title'    => $episode_dubtitle,
								  'post_status'   => 'publish',
								  'post_category' => array( $catgory_id ),
								  'post_author'   => 1,
								);

								// Insert the post into the database
								$postid = wp_update_post( $my_post );

								$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

								update_post_meta($postid,'east_episode',$episode_no);
								update_post_meta($postid,'east_series',$wp_anime_id);
								update_post_meta($postid,'east_player',unserialize($east_player));
								update_post_meta($postid,'east_typesbdb', 'DUB');
								echo "updated post id ".$postid;
							}else{
								$my_post = array(
								  'post_title'    => $episode_dubtitle,
								  'post_status'   => 'publish',
								  'post_category' => array( $catgory_id ),
								  'post_author'   => 1,
								);

								// Insert the post into the database
								$postid = wp_insert_post( $my_post );
								$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

								update_post_meta($postid,'east_episode',$episode_no);
								update_post_meta($postid,'east_series',$wp_anime_id);
								update_post_meta($postid,'east_player',unserialize($east_player));
								update_post_meta($postid,'east_typesbdb', 'DUB');
								echo "Inserted post id ".$postid;
							}
						
					}else{
						
						if($found_post>0){
							$my_post = array(
								'ID'			  => $found_post,
								'post_title'    => $episode_subtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);
							// Insert the post into the database
							$postid = wp_update_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));
							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'SUB');
							
							$my_post = array(
								'ID'			  => $found_post,
								'post_title'    => $episode_dubtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);
							// Insert the post into the database
							$postid = wp_update_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));
							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'DUB');
							echo "updated post id ".$postid;
						}else{
							$my_post = array(
								'post_title'    => $episode_subtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_insert_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_suburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'SUB');
							echo "Inserted post id ".$postid;
							
							$my_post = array(
								'post_title'    => $episode_dubtitle,
								'post_status'   => 'publish',
								'post_category' => array( $catgory_id ),
								'post_author'   => 1,
							);

							// Insert the post into the database
							$postid = wp_insert_post( $my_post );
							$east_player = serialize(array(array("title"=>'Video Stream',"type"=>'urliframe',"url_player"=>$gogoanime_iframe_duburl)));

							update_post_meta($postid,'east_episode',$episode_no);
							update_post_meta($postid,'east_series',$wp_anime_id);
							update_post_meta($postid,'east_player',unserialize($east_player));
							update_post_meta($postid,'east_typesbdb', 'DUB');
							echo "Inserted post id ".$postid;
						}
					}
					
				}
			} else {
				print_r(paramError());
			}
			break;
		default:
			print_r(paramError(true));
			break;
	}

	// Return error parameter
	function paramError($a = false)
	{
		$result = [];
		if ($a) {
			header('HTTP/1.1 404');
			$result['status'] = 404;
			$result['status_message'] = 'Method not found';
			$result['data'] = [];
		} else {
			header('HTTP/1.1 400');
			$result['status'] = 400;
			$result['status_message'] = 'Bad Request';
			$result['data'] = [];
		}

		return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}

	
	
}

function delete_all_between($beginning, $end, $string) {
  $beginningPos = strpos($string, $beginning);
  $endPos = strpos($string, $end);
  if ($beginningPos === false || $endPos === false) {
    return $string;
  }

  $textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);

  return delete_all_between($beginning, $end, str_replace($textToDelete, '', $string)); // recursion to ensure all occurrences are replaced
}

function post_anime( object $args ) {
	$mal_id = $args['id'];
	$episode_type = isset($args['episode_type']) ? $args['episode_type'] : '';
	
	$url = "https://api.eastheme.com/myanimelist/anime/?id=".$mal_id;
	$info = json_decode(file_get_contents($url),true);
	
	$desc_url = 'https://myanimelist.net/anime/'.$mal_id;
	$dom = file_get_html($desc_url, false);
	$content = $dom->find('p[itemprop=description]');
	$full_description = delete_all_between('[',']',$content[0]->plaintext);
	
	if(is_null($info)){
		
	}else{
		if($info['english']){
			$fetch_title= $info['english'];
		}else{
			$fetch_title= $info['title'];
		}
		
		$found_post = post_exists_by_title( $fetch_title,'','','anime');
		
		if($info['genres']){
			$genresArray = explode(',', $info['genres']);
			$genre_list = [];
			foreach($genresArray as $term){
				$term_exist = term_exists($term,'genre')['term_id'];
				if($term_exist>0){
					$genre_list[] = (int)$term_exist;
				}else{
					$term_response = wp_insert_term(
						$term,   // the term 
						'genre', // the taxonomy
						array(
							'description' => $term,
							'slug'        => $term
						)
					);
					$genre_list[] = (int)$term_response['term_id'];
				}
			}
		}
		
		if($info['studios']){
			$studioArray = explode(',', $info['studios']);
			$studio_list = [];
			foreach($studioArray as $term){
				$term_exist = term_exists($term,'studio')['term_id'];
				if($term_exist>0){
					$studio_list[] = (int)$term_exist;
				}else{
					$term_response = wp_insert_term(
						$term,   // the term 
						'studio', // the taxonomy
						array(
							'description' => $term,
							'slug'        => $term
						)
					);
					$studio_list[] = (int)$term_response['term_id'];
				}
			}
		}
		
		if($info['producers']){
			$producersArray = explode(',', $info['producers']);
			$producers_list = [];
			foreach($producersArray as $term){
				$term_exist = term_exists($term,'producers')['term_id'];
				if($term_exist>0){
					$producers_list[] = (int)$term_exist;
				}else{
					$term_response = wp_insert_term(
						$term,   // the term 
						'producers', // the taxonomy
						array(
							'description' => $term,
							'slug'        => $term
						)
					);
					$producers_list[] = (int)$term_response['term_id'];
				}
			}
		}
		
		if($info['premiered']){
			$seasonArray = explode(',', $info['premiered']);
			$season_list = [];
			foreach($seasonArray as $term){
				$term_exist = term_exists($term,'season')['term_id'];
				if($term_exist>0){
					$season_list[] = (int)$term_exist;
				}else{
					$term_response = wp_insert_term(
						$term,   // the term 
						'season', // the taxonomy
						array(
							'description' => $term,
							'slug'        => $term
						)
					);
					$season_list[] = (int)$term_response['term_id'];
				}
			}
		}
		
		
		$catterm_exist = term_exists($fetch_title,'category')['term_id'];
		if($catterm_exist>0){
			$catgory_id = (int)$catterm_exist;
		}else{
			$catterm_response = wp_insert_term(
				$fetch_title,   // the term 
				'category', // the taxonomy
				array(
					'description' => $fetch_title,
					'slug'        => $fetch_title
				)
			);
			$catgory_id = (int)$catterm_response['term_id'];
		}
		$response_success = 0;
		
		if($found_post>0){
			$my_post = array(
				'ID'			=> $found_post,
				'post_title'    => $fetch_title,
				'post_content'  => $full_description,
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_category' => array( $catgory_id ),
				'post_type' =>'anime'
			);
			$postid = wp_update_post( $my_post );
			$genre_res = wp_set_post_terms( $postid, $genre_list, 'genre' );
			wp_set_post_terms( $postid, $studio_list, 'studio' );
			wp_set_post_terms( $postid, $producers_list, 'producers' );
			wp_set_post_terms( $postid, $season_list, 'season' );
			update_post_meta($postid,'east_malid',$mal_id);
// 			update_post_meta($postid,'east_cover',$info['cover']);
			update_post_meta($postid,'east_english',$fetch_title);
			update_post_meta($postid,'east_thumbnail',$info['image']);
			update_post_meta($postid,'east_type',$info['type']);
			update_post_meta($postid,'east_trailer',$info['trailer']);
			update_post_meta($postid,'east_totalepisode',$info['episodes']);
			update_post_meta($postid,'east_score',$info['score']);
			update_post_meta($postid,'east_status',$info['status']);
			update_post_meta($postid,'east_japanese',$info['japanese']);
			update_post_meta($postid,'east_synonyms',$info['synonyms']);
			update_post_meta($postid,'east_duration',$info['duration']);
			update_post_meta($postid,'east_users',$info['users']);
			update_post_meta($postid,'east_source',$info['source']);
			update_post_meta($postid,'east_date',$info['aired']);
			Generate_Featured_Image($info['image'],$postid);
			$response_success = 1;
		}else{
			$my_post = array(
				'post_title'    => $fetch_title,
				'post_content'  => $full_description,
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_category' => array( $catgory_id ),
				'post_type' =>'anime'
			);
			$postid = wp_insert_post( $my_post );
			wp_set_post_terms( $postid, $genre_list, 'genre' );
			wp_set_post_terms( $postid, $studio_list, 'studio' );
			wp_set_post_terms( $postid, $producers_list, 'producers' );
			wp_set_post_terms( $postid, $season_list, 'season' );
			update_post_meta($postid,'east_malid',$mal_id);
// 			update_post_meta($postid,'east_cover',$info['image']);
			update_post_meta($postid,'east_english',$fetch_title);
			update_post_meta($postid,'east_thumbnail',$info['image']);
			update_post_meta($postid,'east_type',$info['type']);
			update_post_meta($postid,'east_trailer',$info['trailer']);
			update_post_meta($postid,'east_totalepisode',$info['episodes']);
			update_post_meta($postid,'east_score',$info['score']);
			update_post_meta($postid,'east_status',$info['status']);
			update_post_meta($postid,'east_japanese',$info['japanese']);
			update_post_meta($postid,'east_synonyms',$info['synonyms']);
			update_post_meta($postid,'east_duration',$info['duration']);
			update_post_meta($postid,'east_users',$info['users']);
			update_post_meta($postid,'east_source',$info['source']);
			update_post_meta($postid,'east_date',$info['aired']);
			Generate_Featured_Image($info['image'],$postid);
			$response_success = 1;
		}
		
		if($response_success>0){
			importing_with_episodes($mal_id,$info['episodes'],$episode_type);
		}
		
		
	}
	
}

function search_anime( object $args ) {
	$search_txt = $args['q'];
	$getMAL = new MyAnimeList;
	$getMAL->pretty = true;
	if(!empty($search_txt)){
		$q = $getMAL->findAnime($search_txt,true);
		$res = json_decode($q,true)['results'];
		$count = count($res);
		echo json_encode($res);
	}
}



function import_anime_series(){
	?>
	<div class="wrap">
		<h1>Import Anime Series</h1>
			<table class="form-table">
				<tr valign="top">
				<th scope="row">Enter Series Name</th>
				<td><input type="text" name="series_name" id="series_name" placeholder="Please Enter Series Name" style="width: 30%;height: 40px;"/></td>
				</tr>
				<tr valign="top">
				<th scope="row">Enter Episodes Type</th>
				<td>
					<select name="episode_type" id="episode_type" style="width: 30%;height: 40px;">
						<option value="sub">Subbed</option>
						<option value="dub">Dubbed</option>
						<option value="both" selected>Both</option>
					</select>
				</td>
				</tr>
			</table>
			<button class="btn btn-info" onclick="import_anime_list()">
				Import Now
			</button>
		 	<div id="result_response"></div>
		
	</div>
	<script>
		var make_api_call = function(mal_id,type){
			jQuery.ajax({url: "/wp-json/anime_api/post_anime/?id="+mal_id+"&episode_type="+type, success: function(response){
				console.log(response);
				jQuery('#result_response').append(response);
			}});
		}
		var import_anime_list=function(){
			var series_name = jQuery('#series_name').val();
			var episode_type = jQuery('#episode_type').val();
			if(series_name=="" || episode_type==""){
				alert("please enter series name that you want to import");
			}else{
				jQuery.ajax({url: "/wp-json/anime_api/search_anime/?q="+series_name, success: function(response){ 
					for(var i=0;i<response.length;i++){
						if(series_name == response[i].title){
							var mal_id = response[i].id;
							make_api_call(mal_id,episode_type);
						}
					}
				}});
			}
		}
	
	</script>

<?php 
} 

function import_episodes(){
?>
	<div class="wrap">
		<h1>Import Episodes List OF Speicific Anime</h1>
		
			<table class="form-table">
				<tr valign="top">
				<th scope="row">Enter Anime ID</th>
				<td><input type="text" name="anime_id" id="anime_id" placeholder="Please Enter Episode ID" style="width: 30%;height: 40px;"/></td>
				</tr>
				
				<tr valign="top">
				<th scope="row">Enter Episodes Type</th>
				<td>
					<select name="episode_type" id="episode_type" style="width: 30%;height: 40px;">
						<option value="sub">Subbed</option>
						<option value="dub">Dubbed</option>
						<option value="both" selected>Both</option>
					</select>
				</td>
				</tr>
			</table>
			<button class="btn btn-info" onclick="import_episodes_list()">
				Import Now
			</button>	
	</div>
	<script>
		
		var import_episodes_list=function(){
			var anime_id = jQuery('#anime_id').val();
// 			var anime_title = jQuery('#anime_title').val();
			var episode_type = jQuery('#episode_type').val();
			if(anime_id=="" || episode_type==''){
				alert("Please Enter Anime Id and Episode Type For Which You Want To Import Episodes List");
			}else{
				jQuery.ajax({url: "/wp-json/anime_api/post_episodes/?method=episode&anime_id="+anime_id+"&episode_type="+episode_type, success: function(response){ 
					console.log(response);
				}});
			}
		}
	
	</script>
<?php 
}

function import_season(){
	echo "Import Season List";
}

function import_genre(){
	echo "Import Genre List";
}
function my_cool_plugin_settings_page() {
?>
<div class="wrap">
<h1>MyAnimeList Custom Plugin For Import Series/Episodes</h1>


</div>
<?php } ?>