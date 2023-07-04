<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

use Contao\Backend;
use Contao\BackendUser;
use Contao\Controller;
use Contao\Database;
use Contao\DataContainer;
use Contao\NewsletterBundle\Security\ContaoNewsletterPermissions;
use Contao\System;

// Add palettes to tl_module
$GLOBALS['TL_DCA']['tl_module']['palettes']['personalData']     = str_replace(',editable', ',editable,newsletters', $GLOBALS['TL_DCA']['tl_module']['palettes']['personalData']);
$GLOBALS['TL_DCA']['tl_module']['palettes']['subscribe']        = '{title_legend},name,headline,type;{config_legend},nl_channels,nl_hideChannels,disableCaptcha;{text_legend},nl_text;{redirect_legend},jumpTo;{email_legend:hide},nl_subscribe;{template_legend:hide},nl_template;{protected_legend:hide},protected;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['unsubscribe']      = '{title_legend},name,headline,type;{config_legend},nl_channels,nl_hideChannels,disableCaptcha;{redirect_legend},jumpTo;{email_legend:hide},nl_unsubscribe;{template_legend:hide},nl_template;{protected_legend:hide},protected;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['newsletterlist']   = '{title_legend},name,headline,type;{config_legend},nl_channels;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['newsletterreader'] = '{title_legend},name,headline,type;{config_legend},nl_channels,overviewPage,customLabel;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';

// Add fields to tl_module
$GLOBALS['TL_DCA']['tl_module']['fields']['newsletters'] = array
(
	'inputType'               => 'checkbox',
	'foreignKey'              => 'tl_newsletter_channel.title',
	'eval'                    => array('multiple'=>true),
	'sql'                     => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['nl_channels'] = array
(
	'inputType'               => 'checkbox',
	'options_callback'        => array('tl_module_newsletter', 'getChannels'),
	'eval'                    => array('multiple'=>true, 'mandatory'=>true),
	'sql'                     => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['nl_text'] = array
(
	'inputType'               => 'textarea',
	'eval'                    => array('rte'=>'tinyMCE', 'helpwizard'=>true),
	'explanation'             => 'insertTags',
	'sql'                     => "text NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['nl_hideChannels'] = array
(
	'inputType'               => 'checkbox',
	'sql'                     => array('type' => 'boolean', 'default' => false)
);

$GLOBALS['TL_DCA']['tl_module']['fields']['nl_subscribe'] = array
(
	'inputType'               => 'textarea',
	'eval'                    => array('style'=>'height:120px', 'decodeEntities'=>true, 'alwaysSave'=>true),
	'load_callback' => array
	(
		array('tl_module_newsletter', 'getSubscribeDefault')
	),
	'sql'                     => "text NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['nl_unsubscribe'] = array
(
	'inputType'               => 'textarea',
	'eval'                    => array('style'=>'height:120px', 'decodeEntities'=>true, 'alwaysSave'=>true),
	'load_callback' => array
	(
		array('tl_module_newsletter', 'getUnsubscribeDefault')
	),
	'sql'                     => "text NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['nl_template'] = array
(
	'inputType'               => 'select',
	'options_callback' => static function () {
		return Controller::getTemplateGroup('nl_');
	},
	'eval'                    => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
	'sql'                     => "varchar(64) COLLATE ascii_bin NOT NULL default ''"
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @internal
 */
class tl_module_newsletter extends Backend
{
	/**
	 * Load the default subscribe text
	 *
	 * @param mixed $varValue
	 *
	 * @return mixed
	 */
	public function getSubscribeDefault($varValue)
	{
		if (trim($varValue) === '')
		{
			$varValue = $GLOBALS['TL_LANG']['tl_module']['text_subscribe'][1];
		}

		return $varValue;
	}

	/**
	 * Load the default unsubscribe text
	 *
	 * @param mixed $varValue
	 *
	 * @return mixed
	 */
	public function getUnsubscribeDefault($varValue)
	{
		if (trim($varValue) === '')
		{
			$varValue = $GLOBALS['TL_LANG']['tl_module']['text_unsubscribe'][1];
		}

		return $varValue;
	}

	/**
	 * Get all channels and return them as array
	 *
	 * @return array
	 */
	public function getChannels(DataContainer $dc)
	{
		$user = BackendUser::getInstance();

		if (!$user->isAdmin && !is_array($user->newsletters))
		{
			return array();
		}

		$strQuery = "SELECT id, title FROM tl_newsletter_channel";

		// Show only channels with a redirect page in the web modules
		if (in_array($dc->activeRecord->type, array('newsletterlist', 'newsletterreader')))
		{
			$strQuery .= " WHERE jumpTo>0";
		}

		$strQuery .= " ORDER BY title";

		$arrChannels = array();
		$objChannels = Database::getInstance()->execute($strQuery);
		$security = System::getContainer()->get('security.helper');

		while ($objChannels->next())
		{
			if ($security->isGranted(ContaoNewsletterPermissions::USER_CAN_EDIT_CHANNEL, $objChannels->id))
			{
				$arrChannels[$objChannels->id] = $objChannels->title;
			}
		}

		return $arrChannels;
	}
}
