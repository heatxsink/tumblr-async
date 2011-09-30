<?php
require_once('EpiCurl.php');
require_once('EpiOAuth.php');
require_once('EpiTumblr.php');

$consumer_key = "consumer_key_here";
$consumer_secret = "consumer_secret_here";
$oauth_callback = "your_oauth_callback_url_here";

function tumblr_oauth($consumer_key, $consumer_secret, $oauth_callback) {
    $tumblr = new EpiTumblr($consumer_key, $consumer_secret);
    $tumblr->setCallback($oauth_callback);
    $authorize_url = $tumblr->getAuthorizeUrl();
    $request_token = $tumblr->getToken();
    var_dump($authorize_url);
    var_dump($request_token);

    return $request_token;
}

function post_media_photo($consumer_key, $consumer_secret, $request_token) {
    $tumblr = new EpiTumblr($consumer_key, $consumer_secret);
    $tumblr->setToken($request_token['token'], $request_token['token_secret']);
    $token = $tumblr->getAccessToken();
    var_dump($token->oauth_token);
    var_dump($token->oauth_token_secret);
    $tumblr->setToken($token->oauth_token, $token->oauth_token_secret);

    //photo - Requires either source or data, but not both. If both are specified, source is used.
    //source - The URL of the photo to copy. This must be a web-accessible URL, not a local file or intranet location.
    //data - An image file. See File uploads below.
    //caption (optional, HTML allowed)
    //click-through-url (optional)

    $post_type = "photo";
    $post_source_url = "image_url_here";
    $post_caption = "caption_here";
    $post_click_through_url = "click_through_url_here";
    $post_generator = "the application that generates the post";
    $request_data = array(
        'send-to-twitter' => "no",
        'type'      => $post_type,
        'source'    => $post_source_url,
        'caption'   => $post_caption,
        'click-through-url' => $post_click_through_url,
        'generator' => $post_generator
    );

    $user_info = $tumblr->post('/api/write', $request_data);
    var_dump($user_info);
}

$request_token = tumblr_oauth($consumer_key, $consumer_secret, $oauth_callback);
post_media_photo($consumer_key, $consumer_secret, $request_token);
