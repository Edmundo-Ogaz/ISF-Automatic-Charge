<?php
/**
 * Storefront engine room
 *
 * @package storefront
 */

/**
 * Assign the Storefront version to a var
 */
$theme              = wp_get_theme( 'storefront' );
$storefront_version = $theme['Version'];

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 980; /* pixels */
}

$storefront = (object) array(
	'version'    => $storefront_version,

	/**
	 * Initialize all the things.
	 */
	'main'       => require 'inc/class-storefront.php',
	'customizer' => require 'inc/customizer/class-storefront-customizer.php',
);

require 'inc/storefront-functions.php';
require 'inc/storefront-template-hooks.php';
require 'inc/storefront-template-functions.php';
require 'inc/wordpress-shims.php';

if ( class_exists( 'Jetpack' ) ) {
	$storefront->jetpack = require 'inc/jetpack/class-storefront-jetpack.php';
}

if ( storefront_is_woocommerce_activated() ) {
	$storefront->woocommerce            = require 'inc/woocommerce/class-storefront-woocommerce.php';
	$storefront->woocommerce_customizer = require 'inc/woocommerce/class-storefront-woocommerce-customizer.php';

	require 'inc/woocommerce/class-storefront-woocommerce-adjacent-products.php';

	require 'inc/woocommerce/storefront-woocommerce-template-hooks.php';
	require 'inc/woocommerce/storefront-woocommerce-template-functions.php';
	require 'inc/woocommerce/storefront-woocommerce-functions.php';
}

if ( is_admin() ) {
	$storefront->admin = require 'inc/admin/class-storefront-admin.php';

	require 'inc/admin/class-storefront-plugin-install.php';
}

/**
 * NUX
 * Only load if wp version is 4.7.3 or above because of this issue;
 * https://core.trac.wordpress.org/ticket/39610?cversion=1&cnum_hist=2
 */
if ( version_compare( get_bloginfo( 'version' ), '4.7.3', '>=' ) && ( is_admin() || is_customize_preview() ) ) {
	require 'inc/nux/class-storefront-nux-admin.php';
	require 'inc/nux/class-storefront-nux-guided-tour.php';
	require 'inc/nux/class-storefront-nux-starter-content.php';
}

/**
 * Note: Do not add any custom code here. Please use a custom plugin so that your customizations aren't lost during updates.
 * https://github.com/woocommerce/theme-customisations
 */

$form_id = 6;
$url = 'https://sandbox.flow.cl/api';
$url_return = "https://isf-chile.org/dona-aqui-3/donacion-exitosa/";
//$apiKey = "313FF483-7A68-4ED2-97BD-6EE0E7L54F9B"; //OWNER
//$secretKey = "14075e57b381b19e510931c4fea00413ec723473"; //OWNER
$apiKey = "28FBD69C-7ABE-4831-B14F-327L155BCAAD"; //ISF
$secretKey = "8a65b343d1dbbfba3e34fcf9eb0644dad21cc5b8"; //ISF

$makeSign = function ($params) use ($secretKey) {

    $keys = array_keys($params);
    sort($keys);

    $toSign = "";
    foreach($keys as $key) {
        $toSign .= $key . $params[$key];
    };

    $sign = hash_hmac('sha256', $toSign, $secretKey);

    return $sign;
};

function restPostCall($url, $data) {

    logger("restPostCall "."url ".$url." data ".json_encode($data));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    $headers = array(
        'Content-Type: multipart/form-data',
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);

    if ( ! curl_errno($ch) ) {
        $codigoReturn= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    } else {
        $response = null;
    }
    curl_close($ch);

    $resp = json_decode($response);
    logger("restPostCall "."response ".$response);

    return $resp;
}

function restGetCall($url, $params) {
    $url = $url . "?" . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);

    if ( ! curl_errno($ch) ) {
        $codigoReturn= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    } else {
        $response = null;
    }
    curl_close($ch);

    logger("restGetCall "."response ".$response);

    return $response;
}

add_filter('wpcf7_form_tag', function ($tag, $unused) use ($url, $apiKey, $makeSign) {

    if ( $tag['name'] != 'cf7-dropdown-monto' )
        return $tag;

    logger("wpcf7_form_tag "."tag ".json_encode($tag)." unused ".json_encode($unused));

    $params = array(
        "apiKey" => $apiKey,
    );

    $sign = $makeSign($params);

    $params = array_merge($params, array("s" => $sign));

    $response = restGetCall($url.'/plans/list', $params);

    if ( isset($response) ) {
        $amounts = array();
        $body = json_decode($response, true);
	    foreach ($body["data"] as $valor) {
	        $tag['raw_values'][] = $valor["planId"];
            $tag['labels'][] = intval($valor["amount"]);
        }
        /*$tag['raw_values'][] = "otro-monto";
        $tag['labels'][] = "Otro monto";*/
    }

    $pipes = new WPCF7_Pipes($tag['raw_values']);
    $tag['values'] = $pipes->collect_befores();
    $tag['pipes'] = $pipes;

    return $tag;
}, 10, 2);

add_filter('wpcf7_skip_mail','__return_true');

function logger($message){
    error_log($message);
}

add_filter('wpcf7_validate_text', function($result, $tag) {
    /*if ( "otro-monto" != $_POST['cf7-dropdown-monto'] ) {*/

        $tag = new WPCF7_FormTag($tag);

        if ('rut' == $tag->name) {
        	$rut = isset($_POST['rut']) ? trim($_POST['rut']) : '';

        	logger("wpcf7_validate_text "."rut ".$rut);

        	if ( empty($rut) )
		    $result->invalidate($tag, "Debe ingresar rut");
		else if ( ! valida_rut($rut) )
                    $result->invalidate($tag, "Rut inválido");
        }
        if ('nombre' == $tag->name) {
        	$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

        	logger("wpcf7_validate_text "."nombre ".$nombre);

        	if ( empty($nombre) )
                $result->invalidate($tag, "Debe ingresar nombre");
        }
    /*}*/
    return $result;
},20,2);

add_filter('wpcf7_validate_email', function($result, $tag) {
    /*if ( "otro-monto" != $_POST['cf7-dropdown-monto'] ) {*/
        $tag = new WPCF7_FormTag($tag);

        if ('email' == $tag->name) {
        	$email = isset($_POST['email']) ? trim($_POST['email']) : '';

        	logger("wpcf7_validate_email "."email ".$email);

        	if ( empty($email) )
                $result->invalidate($tag, "Debe ingresar email");
        }
    /*}*/
    return $result;
},20,2);

add_filter('wpcf7_validate_select', function($result, $tag) {
    /*if ( "otro-monto" != $_POST['cf7-dropdown-monto'] ) {*/
        $tag = new WPCF7_FormTag($tag);

        if ('cf7-dropdown-monto' == $tag->name) {
	        $monto = isset($_POST['cf7-dropdown-monto']) ? $_POST['cf7-dropdown-monto'] : '';

	        logger("wpcf7_validate_select "."monto ".$monto);

	        if ( empty($monto) )
                $result->invalidate($tag, "Debe selecctionar monto");
        }
    /*}*/
    return $result;
},20,2);

/*add_filter('wpcf7_validate_checkbox', function($result, $tag) {
    $tag = new WPCF7_FormTag($tag);

    if ('otro-monto' == $tag->name) {
	    $monto = isset($_POST['cf7-dropdown-monto']) ? $_POST['cf7-dropdown-monto'] : '';
	    $otroMonto = isset($_POST['otro-monto']) ? $_POST['otro-monto'] : '';

	    logger("wpcf7_validate_checkbox "."monto ".$monto." otro-monto ".json_encode($otroMonto));

	    if ( empty($monto) && empty($otroMonto))
            $result->invalidate($tag, "Debe selecctionar monto u otro monto");
    }
    return $result;
},20,2);*/

add_filter('wpcf7_form_hidden_fields',function($fields) use ($form_id) {
  $nonce = wp_create_nonce('cf7-redirect-id');
  $fields['redirect_nonce'] = $nonce;

  add_filter('do_shortcode_tag', function($output, $tag, $attrs) use ($nonce, $form_id) {

    logger("do_shortcode_tag "."tag ".$tag." attrs ".json_encode($attrs));

    if( $tag != "contact-form-7" || $attrs['id'] != $form_id )
        return $output;

    $script = '<script>'.PHP_EOL;
    $script .= 'document.addEventListener( "wpcf7mailsent", function( event ){'.PHP_EOL;
    $script .= '    location = "'.esc_url_raw(home_url('/formulario/?cf7='.$nonce)).'";'.PHP_EOL;
    $script .= '  });'.PHP_EOL;
    $script .= '</script>'.PHP_EOL;
    return $output.PHP_EOL.$script;
  },10,3);
  return $fields;
});

/*add_action('wpcf7_mail_sent', function($contact_form) use ($form_id, $url, $apiKey, $makeSign, $url_return) {

    if ( $contact_form->id !== $form_id || ! isset($_POST['redirect_nonce']) )
        return;

    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();

    logger("wpcf7_mail_sent ".json_encode($posted_data));

    try {

        $params = array(
            "apiKey" => $apiKey,
            "name" => $posted_data["nombre"],
            "email" => $posted_data["email"],
            "externalId" => $posted_data["rut"]
        );
        $sign = $makeSign($params);
        $params = array_merge($params, array("s" => $sign));
        $response = restPostCall($url."/customer/create", $params);

        if ( isset($response) && ! isset($response->code) ) {
            $params = array(
                "apiKey" => $apiKey,
                "planId" => $posted_data["cf7-dropdown-monto"][0],
                "customerId" => $response->customerId,
            );
            $sign = $makeSign($params);
            $params = array_merge($params, array("s" => $sign));
            $response = restPostCall($url."/subscription/create", $params);

            if ( isset($response) && ! isset($response->code) ) {
                $params = array(
                    "apiKey" => $apiKey,
                    "customerId" => $response->customerId,
                    "url_return" => $url_return
                );

                $sign = $makeSign($params);

                $params = array_merge($params, array("s" => $sign));

                $response = restPostCall($url."/customer/register", $params);

                if ( isset($response) && ! isset($response->code)) {
                    $url_flow = $response->url;
                    $token = $response->token;
                } else {
                    logger("customerCardRegister Something went wrong");
                    throw new Exception($response->message);
                }
            } else {
                logger("subscriptionCreate Something went wrong");
                throw new Exception($response->message);
            }
        } else {
            logger("customerCreate Something went wrong");
            throw new Exception($response->message);
        }
    } catch (Exception $e) {
        logger($e->getMessage());
        exit;
    }

    logger("wpcf7_mail_sent "."url_flow ".$url_flow." token ".$token);

    set_transient('_cf7_data_'.$_POST['redirect_nonce'], $url_flow."?token=".$token, 5*60); //5 min expiration.
});*/

add_action('wpcf7_before_send_mail', function($contact_form, &$abort, $object) use ($form_id, $url, $apiKey, $makeSign, $url_return) {
    logger("wpcf7_before_send_mail "."form ".json_encode($contact_form)." object ".json_encode($object));

    if ( $contact_form->id !== $form_id || ! isset($_POST['redirect_nonce']) )
        return;

    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();

    logger("wpcf7_before_send_mail ".json_encode($posted_data));

    try {

        $params = array(
            "apiKey" => $apiKey,
            "name" => $posted_data["nombre"],
	    "email" => $posted_data["email"],
	    "externalId" => formater_rut($posted_data["rut"])
        );
        $sign = $makeSign($params);
        $params = array_merge($params, array("s" => $sign));
        $response = restPostCall($url."/customer/create", $params);

        if ( isset($response) && ! isset($response->code) ) {
            $params = array(
                "apiKey" => $apiKey,
                "planId" => $posted_data["cf7-dropdown-monto"][0],
                "customerId" => $response->customerId,
            );
            $sign = $makeSign($params);
            $params = array_merge($params, array("s" => $sign));
            $response = restPostCall($url."/subscription/create", $params);

            if ( isset($response) && ! isset($response->code) ) {
                $params = array(
                    "apiKey" => $apiKey,
                    "customerId" => $response->customerId,
                    "url_return" => $url_return
                );

                $sign = $makeSign($params);

                $params = array_merge($params, array("s" => $sign));

                $response = restPostCall($url."/customer/register", $params);

                if ( isset($response) && ! isset($response->code)) {
                    $url_flow = $response->url;
                    $token = $response->token;
                } else {
                    logger("customerCardRegister Something went wrong");
                    throw new Exception($response->message);
                }
            } else {
                logger("subscriptionCreate Something went wrong");
                throw new Exception($response->message);
            }
        } else {
            logger("customerCreate Something went wrong");
            throw new Exception($response->message);
	}

	logger("wpcf7_mail_sent "."url_flow ".$url_flow." token ".$token);

	set_transient('_cf7_data_'.$_POST['redirect_nonce'], $url_flow."?token=".$token, 5*60); //5 min expiration.

    } catch (Exception $e) {
        logger($e->getMessage());
        $abort = true;
        $object->set_response($e->getMessage());
    }

  /*$error = 1;
  if($error != 0) {
      $abort = true;
      $object->set_response("An error happened");
  }*/
}, 10, 3);

add_action('init', function() {
  if( isset($_GET['cf7']) ) {

    logger("init cf7");

    $transient = '_cf7_data_'.$_GET['cf7'];
    $url = get_transient($transient);

    logger("init cf7 "."url ".$url);
    wp_redirect($url);
    exit();
  }
});

function valida_rut($rut)
{
    $rut = preg_replace('/[^k0-9]/i', '', $rut);
    $dv  = substr($rut, -1);
    $numero = substr($rut, 0, strlen($rut)-1);
    $i = 2;
    $suma = 0;
    foreach(array_reverse(str_split($numero)) as $v)
    {
        if($i==8)
            $i = 2;

        $suma += $v * $i;
        ++$i;
    }

    $dvr = 11 - ($suma % 11);

    if($dvr == 11)
        $dvr = 0;
    if($dvr == 10)
        $dvr = 'K';

    if($dvr == strtoupper($dv))
        return true;
    else
        return false;
}

function formater_rut( $rut ){
    $rut = preg_replace('/[^-k0-9]/i', '', $rut);
	$rutTmp = explode( "-", $rut );
	return number_format( $rutTmp[0], 0, "", ".") . '-' . $rutTmp[1];
}
