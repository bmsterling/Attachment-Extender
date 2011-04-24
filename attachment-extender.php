<?php
/**
	Plugin Name: Attachment Extender
	Plugin URI: http://benjaminsterling.com/wordpress-attachment-extender/
	Description: Give you the ability to update/upload new pictues, pdf, any file type that you have in your Media Library.  It also let you insert more then one file into a post at a time
	Author: Benjamin Sterling
	Version: 0.3.1
	Author URI: http://benjaminsterling.com
*/
/**
 *	Load WP-Config File If This File Is Called Directly
 */
if (!function_exists('add_action')) {
	require_once('../../../wp-load.php');
	require_once('../../../wp-admin/includes/media.php');
} //  end : if (!function_exists('add_action'))

if( isset($_GET['extra']) && !empty($_GET['extra']) ){
	if ( $_GET['extra'] == 'image' ){
		if( empty( $_GET['url'] ) ){
			echo get_image_tag($_GET['id'], $_GET['alt'], $_GET['title'], $_GET['align'], $_GET['size']);
		}
		else{
			echo get_image_send_to_editor($_GET['id'], $_GET['alt'], $_GET['title'], $_GET['align'], $_GET['url'], false, $_GET['size']);
		};
	};
	exit(0);
}

add_filter('attachment_fields_to_edit',	'attachment_fields_to_edit', 1, 2);

function attachment_fields_to_edit($form_fields, $post){

	$form_fields['async-upload']  = array(
		'label'      => __('New Upload'),
		'input'      => 'html',
		'html' => "<input type='file' name='attachments[$post->ID][ae-upload]' value='' />"
	);

	return $form_fields;
}//async-upload

add_filter('attachment_fields_to_save',	'wp_ae_attachment_fields_to_save', 1, 2);

function wp_ae_attachment_fields_to_save($form_fields, $post){

	$form_fields['ae-upload']  = array(
		'label'      => __('New Upload'),
		'input'      => 'html',
		'html' => "<input type='file' name='attachments[$post->ID][ae-upload]' value='' />"
	);

	return $form_fields;
}//async-upload

add_action('edit_attachment',	'wp_ae_edit_attachment');
function wp_ae_edit_attachment($id){
	$thumbnail['w'] = get_option("thumbnail_size_w");
	$thumbnail['h'] = get_option("thumbnail_size_h");
	$thumbnail['c'] = get_option("thumbnail_crop");

	$medium['w'] = get_option("medium_size_w");
	$medium['h'] = get_option("medium_size_h");
	$medium['c'] = get_option("medium_crop");

	$files = array();
	foreach ($_FILES['attachments'] as $k => $l) {
		foreach ($l as $i => $v) {
			if (!array_key_exists($i, $files)){
				$files[$i] = array();
			}
			$files[$i][$k] = $v['ae-upload'];
		}
	} 
	
	foreach( $files as $k => $file ){
		if (!isset($file) || !is_uploaded_file($file["tmp_name"]) || $file["error"] != 0) {}
		else{
		

		
			$post = wp_get_single_post($k, ARRAY_A);
			include_once('class.upload.php');
			$uploads = wp_upload_dir($post['post_date']);
			$handle = new Upload($file);
			$handle->file_overwrite = true;
			$handle->file_auto_rename = false;
			$handle->file_new_name_body = $post['post_name'];
			
			if ($handle->uploaded) {
				$path = explode('/', get_attached_file($k, true) );
				array_pop($path);
				$path = join( '/', $path );
				$imagedata = wp_get_attachment_metadata( $k );

				$handle->process($uploads['path']);

				if( $handle->file_is_image ){
					$tmppath = $path . '/' . $imagedata['sizes']['thumbnail']['file'];
					
					if( file_exists( $tmppath ) ){
						@unlink( $tmppath );
					}
				
				
					$handle->image_resize		= true;
					$handle->image_ratio		= true;
					$handle->image_ratio_crop	= $thumbnail['c'];
					$handle->image_x            = $thumbnail['w'];
					$handle->image_y            = $thumbnail['h'];
					$handle->file_new_name_body = $post['post_name'] . "-{$thumbnail['w']}x{$thumbnail['h']}";
					$handle->process($uploads['path']);
	
					$imagedata['sizes']['thumbnail']['file'] = $handle->file_dst_name;
					$imagedata['sizes']['thumbnail']['width'] = $handle->image_dst_x;
					$imagedata['sizes']['thumbnail']['height'] = $handle->image_dst_h;
		
					$tmppath = $path . '/' . $imagedata['sizes']['medium']['file'];
					
					if( file_exists( $tmppath ) ){
						@unlink( $tmppath );
					}
	
					$handle->image_resize		= true;
					$handle->image_ratio		= true;
					$handle->image_ratio_crop	= $medium['c'];
					$handle->image_x            = $medium['w'];
					$handle->image_y            = $medium['h'];
					$handle->file_new_name_body = $post['post_name'] . "-{$medium['w']}x{$medium['h']}";
					$handle->process($uploads['path']);
	
					$imagedata['sizes']['medium']['file'] = $handle->file_dst_name;
					$imagedata['sizes']['medium']['width'] = $handle->image_dst_x;
					$imagedata['sizes']['medium']['height'] = $handle->image_dst_h;
				}
	
				if ($handle->processed) {
					wp_update_attachment_metadata($k, $imagedata );
					$handle->clean();
				} else {}
			}
		}
	}

}


add_action('admin_head', 'wp_ae_add_js');

function wp_ae_add_js(){

$blogsurl = get_bloginfo('wpurl') . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/attachment-extender.php';
echo "
<script type=\"text/javascript\">
jQuery(document).ready(function(){
	jQuery('#media-single-form').attr('enctype','multipart/form-data');
	/*jQuery('div.filename').filter('.new').hide();*/
	var jqinsertAllbutton = jQuery('<input type=\"submit\" class=\"button insertAllbutton\" name=\"insertall\" value=\"". esc_attr( __( 'Insert all checked' ) ) . "\"/>')
	.appendTo('#library-form');
	
jQuery('.media-item')
.each(function(){
	try{
		var jqthis = jQuery(this);
		var id = jqthis.attr('id');
		id = id.split('-').pop();
		//jQuery('.filename', jqthis).filter('.new')
		jqthis.prepend('<input type=\"checkbox\" name=\"insertme\" value=\"'+id +'\" style=\"float:right;margin-top:10px;margin-right:10px;\"/>');
	}catch(e){}
});

jqinsertAllbutton
.click(function(){
	var theChecked = jQuery('input[name=insertme]:checked');
	
	theChecked.each(function(){
		var el = jQuery(this);
		var table = el.siblings('table');
		var id = el.val();
		var fileType = jQuery('#type-of-'+id).val();
		var title = jQuery('tr.post_title td.field input',table).val();
		var excerpt = jQuery('tr.post_excerpt td.field input',table).val();
		var content = jQuery('tr.post_content td.field textarea',table).val();
		var url = jQuery('tr.url td.field input',table).val();

		var file = '';
		if( fileType == 'image' ){
			var size = jQuery('tr.image-size td.field input:checked',table).val();
			var align = jQuery('tr.align td.field input:checked',table).val();
			jQuery.ajax({
				url: '" . $blogsurl . "',
				data : {extra:'image',id:id,title:title, align:align, size:size,url:url},
				success:function(data){
					ae_send_to_editor(data);
					el.attr('checked',false);
				}
			});
		}
		else{
			if( url == '' ){
				ae_send_to_editor(title);
			}
			else{
				ae_send_to_editor('<a href=\"'+url+'\">'+title+'</a>');
			};
					el.attr('checked',false);
		};
	});
	return false;
});
	
});

function ae_send_to_editor(h) {
	var win = window.opener ? window.opener : window.dialogArguments;
	if ( !win )
		win = top;
	tinyMCE = win.tinyMCE;
	if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.getInstanceById('content') ) && !ed.isHidden() ) {
		tinyMCE.selectedInstance.getWin().focus();
		tinyMCE.execCommand('mceInsertContent', false, h);
	} else
		win.edInsertContent(win.edCanvas, h);
};
</script>
";
}
?>
