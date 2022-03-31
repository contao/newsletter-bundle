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
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\DataContainer;
use Contao\Date;
use Contao\DC_Table;
use Contao\Environment;
use Contao\Input;
use Contao\NewsletterChannelModel;
use Contao\StringUtil;
use Contao\System;

$GLOBALS['TL_DCA']['tl_newsletter'] = array
(
	// Config
	'config' => array
	(
		'dataContainer'               => DC_Table::class,
		'ptable'                      => 'tl_newsletter_channel',
		'enableVersioning'            => true,
		'markAsCopy'                  => 'subject',
		'onload_callback' => array
		(
			array('tl_newsletter', 'checkPermission')
		),
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'pid' => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => DataContainer::MODE_PARENT,
			'fields'                  => array('sent', 'date'),
			'headerFields'            => array('title', 'jumpTo', 'tstamp', 'sender'),
			'panelLayout'             => 'filter;sort,search,limit',
			'child_record_callback'   => array('tl_newsletter', 'listNewsletters')
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg'
			),
			'copy' => array
			(
				'href'                => 'act=paste&amp;mode=copy',
				'icon'                => 'copy.svg'
			),
			'cut' => array
			(
				'href'                => 'act=paste&amp;mode=cut',
				'icon'                => 'cut.svg'
			),
			'delete' => array
			(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
			),
			'show' => array
			(
				'href'                => 'act=show',
				'icon'                => 'show.svg'
			),
			'send' => array
			(
				'href'                => 'key=send',
				'icon'                => 'bundles/contaonewsletter/send.svg'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'__selector__'                => array('addFile'),
		'default'                     => '{title_legend},subject,alias;{html_legend},content;{text_legend:hide},text;{attachment_legend},addFile;{template_legend:hide},template;{sender_legend:hide},mailerTransport,sender,senderName;{expert_legend:hide},sendText,externalImages'
	),

	// Subpalettes
	'subpalettes' => array
	(
		'addFile'                     => 'files'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array
		(
			'foreignKey'              => 'tl_newsletter_channel.title',
			'sql'                     => "int(10) unsigned NOT NULL default 0",
			'relation'                => array('type'=>'belongsTo', 'load'=>'lazy')
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'subject' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => DataContainer::SORT_INITIAL_LETTER_ASC,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'decodeEntities'=>true, 'maxlength'=>128, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'alias' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'alias', 'doNotCopy'=>true, 'unique'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'save_callback' => array
			(
				array('tl_newsletter', 'generateAlias')
			),
			'sql'                     => "varchar(255) BINARY NOT NULL default ''"
		),
		'content' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('rte'=>'tinyNews', 'helpwizard'=>true),
			'explanation'             => 'insertTags',
			'load_callback' => array
			(
				array('tl_newsletter', 'convertAbsoluteLinks')
			),
			'save_callback' => array
			(
				array('tl_newsletter', 'convertRelativeLinks')
			),
			'sql'                     => "mediumtext NULL"
		),
		'text' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('decodeEntities'=>true, 'class'=>'noresize'),
			'sql'                     => "mediumtext NULL"
		),
		'addFile' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('submitOnChange'=>true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'files' => array
		(
			'exclude'                 => true,
			'inputType'               => 'fileTree',
			'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox', 'filesOnly'=>true, 'mandatory'=>true),
			'sql'                     => "blob NULL"
		),
		'template' => array
		(
			'exclude'                 => true,
			'inputType'               => 'select',
			'eval'                    => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
			'options_callback' => static function ()
			{
				return Controller::getTemplateGroup('mail_');
			},
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'sendText' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'externalImages' => array
		(
			'exclude'                 => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'mailerTransport' => array
		(
			'exclude'                 => true,
			'inputType'               => 'select',
			'eval'                    => array('tl_class'=>'w50', 'includeBlankOption'=>true),
			'options_callback'        => array('contao.mailer.available_transports', 'getTransportOptions'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'sender' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'filter'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'email', 'maxlength'=>255, 'decodeEntities'=>true, 'tl_class'=>'w50 clr'),
			'load_callback' => array
			(
				array('tl_newsletter', 'addSenderPlaceholder')
			),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'senderName' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => DataContainer::SORT_ASC,
			'inputType'               => 'text',
			'eval'                    => array('decodeEntities'=>true, 'maxlength'=>128, 'tl_class'=>'w50'),
			'load_callback' => array
			(
				array('tl_newsletter', 'addSenderNamePlaceholder')
			),
			'sql'                     => "varchar(128) NOT NULL default ''"
		),
		'sent' => array
		(
			'filter'                  => true,
			'sorting'                 => true,
			'flag'                    => DataContainer::SORT_ASC,
			'eval'                    => array('doNotCopy'=>true, 'isBoolean'=>true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'date' => array
		(
			'filter'                  => true,
			'sorting'                 => true,
			'flag'                    => DataContainer::SORT_MONTH_DESC,
			'eval'                    => array('rgxp'=>'datim'),
			'sql'                     => "varchar(10) NOT NULL default ''"
		)
	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 */
class tl_newsletter extends Backend
{
	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import(BackendUser::class, 'User');
	}

	/**
	 * Check permissions to edit table tl_newsletter
	 *
	 * @throws AccessDeniedException
	 */
	public function checkPermission()
	{
		if ($this->User->isAdmin)
		{
			return;
		}

		// Set root IDs
		if (empty($this->User->newsletters) || !is_array($this->User->newsletters))
		{
			$root = array(0);
		}
		else
		{
			$root = $this->User->newsletters;
		}

		$id = strlen(Input::get('id')) ? Input::get('id') : CURRENT_ID;

		// Check current action
		switch (Input::get('act'))
		{
			case 'paste':
			case 'select':
				// Check CURRENT_ID here (see #247)
				if (!in_array(CURRENT_ID, $root))
				{
					throw new AccessDeniedException('Not enough permissions to access newsletter channel ID ' . $id . '.');
				}
				break;

			case 'create':
				if (!Input::get('pid') || !in_array(Input::get('pid'), $root))
				{
					throw new AccessDeniedException('Not enough permissions to create newsletters in channel ID ' . Input::get('pid') . '.');
				}
				break;

			case 'cut':
			case 'copy':
				if (!in_array(Input::get('pid'), $root))
				{
					throw new AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' newsletter ID ' . $id . ' to channel ID ' . Input::get('pid') . '.');
				}
				// no break

			case 'edit':
			case 'show':
			case 'delete':
				$objChannel = $this->Database->prepare("SELECT pid FROM tl_newsletter WHERE id=?")
											 ->limit(1)
											 ->execute($id);

				if ($objChannel->numRows < 1)
				{
					throw new AccessDeniedException('Invalid newsletter ID ' . $id . '.');
				}

				if (!in_array($objChannel->pid, $root))
				{
					throw new AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' newsletter ID ' . $id . ' of newsletter channel ID ' . $objChannel->pid . '.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
			case 'cutAll':
			case 'copyAll':
				if (!in_array($id, $root))
				{
					throw new AccessDeniedException('Not enough permissions to access newsletter channel ID ' . $id . '.');
				}

				$objChannel = $this->Database->prepare("SELECT id FROM tl_newsletter WHERE pid=?")
											 ->execute($id);

				$objSession = System::getContainer()->get('session');

				$session = $objSession->all();
				$session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $objChannel->fetchEach('id'));
				$objSession->replace($session);
				break;

			default:
				if (Input::get('act'))
				{
					throw new AccessDeniedException('Invalid command "' . Input::get('act') . '".');
				}

				if (Input::get('key') == 'send')
				{
					$objChannel = $this->Database->prepare("SELECT pid FROM tl_newsletter WHERE id=?")
												 ->limit(1)
												 ->execute($id);

					if ($objChannel->numRows < 1)
					{
						throw new AccessDeniedException('Invalid newsletter ID ' . $id . '.');
					}

					if (!in_array($objChannel->pid, $root))
					{
						throw new AccessDeniedException('Not enough permissions to send newsletter ID ' . $id . ' of newsletter channel ID ' . $objChannel->pid . '.');
					}
				}
				elseif (!in_array($id, $root))
				{
					throw new AccessDeniedException('Not enough permissions to access newsletter channel ID ' . $id . '.');
				}
				break;
		}
	}

	/**
	 * List records
	 *
	 * @param array $arrRow
	 *
	 * @return string
	 */
	public function listNewsletters($arrRow)
	{
		return '
<div class="cte_type ' . (($arrRow['sent'] && $arrRow['date']) ? 'published' : 'unpublished') . '"><strong>' . $arrRow['subject'] . '</strong> - ' . (($arrRow['sent'] && $arrRow['date']) ? sprintf($GLOBALS['TL_LANG']['tl_newsletter']['sentOn'], Date::parse(Config::get('datimFormat'), $arrRow['date'])) : $GLOBALS['TL_LANG']['tl_newsletter']['notSent']) . '</div>
<div class="limit_height' . (!Config::get('doNotCollapse') ? ' h85' : '') . '">' . (!$arrRow['sendText'] ? '
' . StringUtil::insertTagToSrc($arrRow['content']) . '<hr>' : '') . '
<pre style="white-space:pre-wrap">' . $arrRow['text'] . '</pre>
</div>' . "\n";
	}

	/**
	 * Convert absolute URLs from TinyMCE to relative URLs
	 *
	 * @param string $strContent
	 *
	 * @return string
	 */
	public function convertAbsoluteLinks($strContent)
	{
		return str_replace('src="' . Environment::get('base'), 'src="', $strContent);
	}

	/**
	 * Convert relative URLs from TinyMCE to absolute URLs
	 *
	 * @param string $strContent
	 *
	 * @return string
	 */
	public function convertRelativeLinks($strContent)
	{
		return $this->convertRelativeUrls($strContent);
	}

	/**
	 * Auto-generate the newsletter alias if it has not been set yet
	 *
	 * @param mixed         $varValue
	 * @param DataContainer $dc
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function generateAlias($varValue, DataContainer $dc)
	{
		$aliasExists = function (string $alias) use ($dc): bool
		{
			return $this->Database->prepare("SELECT id FROM tl_newsletter WHERE alias=? AND id!=?")->execute($alias, $dc->id)->numRows > 0;
		};

		// Generate alias if there is none
		if (!$varValue)
		{
			$varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->subject, NewsletterChannelModel::findByPk($dc->activeRecord->pid)->jumpTo, $aliasExists);
		}
		elseif (preg_match('/^[1-9]\d*$/', $varValue))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'], $varValue));
		}
		elseif ($aliasExists($varValue))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
		}

		return $varValue;
	}

	/**
	 * Add the sender address as placeholder
	 *
	 * @param mixed         $varValue
	 * @param DataContainer $dc
	 *
	 * @return mixed
	 */
	public function addSenderPlaceholder($varValue, DataContainer $dc)
	{
		if ($dc->activeRecord && $dc->activeRecord->pid)
		{
			$objChannel = $this->Database->prepare("SELECT sender FROM tl_newsletter_channel WHERE id=?")
										 ->execute($dc->activeRecord->pid);

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['placeholder'] = $objChannel->sender;
		}

		return $varValue;
	}

	/**
	 * Add the sender name as placeholder
	 *
	 * @param mixed         $varValue
	 * @param DataContainer $dc
	 *
	 * @return mixed
	 */
	public function addSenderNamePlaceholder($varValue, DataContainer $dc)
	{
		if ($dc->activeRecord && $dc->activeRecord->pid)
		{
			$objChannel = $this->Database->prepare("SELECT senderName FROM tl_newsletter_channel WHERE id=?")
										 ->execute($dc->activeRecord->pid);

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['placeholder'] = $objChannel->senderName;
		}

		return $varValue;
	}
}
