<?php
/**
 * Template autoload definition for xrowdwd
 *
 * @copyright Copyright (C) 2012-2012 xrow GmbH
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2
 *
 */

$eZTemplateOperatorArray = array();

$eZTemplateOperatorArray[] = array( 'script' => 'extension/xrowdwd/autoloads/xrowdwdtemplateoperators.php',
                                    'class' => 'xrowDWDTemplateOperators',
                                    'operator_names' => array( 'getDWDdata' ) );
?>