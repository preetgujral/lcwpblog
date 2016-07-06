<?php
if(isset($includeCss) && is_array($includeCss))
{
    foreach ($includeCss as $css)
    {
        echo '<link rel="stylesheet" type="text/css" href="' . SG_PUBLIC_URL . 'css/' . $css . '.css' . '">';
    }
}
?>

<?php
	if(SGConfig::get('SG_EXCEPTION_TIMEOUT_ERROR')) {
		require_once(SG_PUBLIC_INCLUDE_PATH.'notification.'.strtolower(SG_ENV_ADAPTER).'.php');
	}
?>
<div class="sg-spinner"></div>
<div class="sg-wrapper-less" style="width: 80%;">
    <div id="sg-wrapper">
