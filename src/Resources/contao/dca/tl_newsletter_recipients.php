<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_newsletter_recipients'] = array
(
	// Config
	'config' => array
	(
		'dataContainer'               => Contao\DC_Table::class,
		'ptable'                      => 'tl_newsletter_channel',
		'enableVersioning'            => true,
		'onload_callback' => array
		(
			array('tl_newsletter_recipients', 'checkPermission')
		),
		'oncut_callback' => array
		(
			array('tl_newsletter_recipients', 'clearOptInData')
		),
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'pid,email' => 'unique',
				'email' => 'index',
				'active' => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 4,
			'fields'                  => array('email'),
			'panelLayout'             => 'filter;sort,search,limit',
			'headerFields'            => array('title', 'jumpTo', 'tstamp', 'sender'),
			'child_record_callback'   => array('tl_newsletter_recipients', 'listRecipient')
		),
		'global_operations' => array
		(
			'import' => array
			(
				'href'                => 'key=import',
				'class'               => 'header_css_import',
				'attributes'          => 'onclick="Backend.getScrollOffset()"'
			),
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
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
			),
			'toggle' => array
			(
				'icon'                => 'visible.svg',
				'attributes'          => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
				'button_callback'     => array('tl_newsletter_recipients', 'toggleIcon')
			),
			'show' => array
			(
				'href'                => 'act=show',
				'icon'                => 'show.svg'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{email_legend},email,active',
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
		'email' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp'=>'email', 'maxlength'=>255, 'decodeEntities'=>true, 'tl_class'=>'w50'),
			'save_callback' => array
			(
				array('tl_newsletter_recipients', 'checkUniqueRecipient'),
				array('tl_newsletter_recipients', 'checkBlacklistedRecipient')
			),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'active' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50 m12'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'addedOn' => array
		(
			'filter'                  => true,
			'sorting'                 => true,
			'flag'                    => 8,
			'eval'                    => array('rgxp'=>'datim', 'doNotCopy'=>true),
			'sql'                     => "varchar(10) NOT NULL default ''"
		)
	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_newsletter_recipients extends Contao\Backend
{
	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('Contao\BackendUser', 'User');
	}

	/**
	 * Check permissions to edit table tl_newsletter_recipients
	 *
	 * @throws Contao\CoreBundle\Exception\AccessDeniedException
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

		$id = strlen(Contao\Input::get('id')) ? Contao\Input::get('id') : CURRENT_ID;

		// Check current action
		switch (Contao\Input::get('act'))
		{
			case 'paste':
			case 'select':
				// Check CURRENT_ID here (see #247)
				if (!in_array(CURRENT_ID, $root))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to access newsletter channel ID ' . $id . '.');
				}
				break;

			case 'create':
				if (!Contao\Input::get('pid') || !in_array(Contao\Input::get('pid'), $root))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to create newsletters recipients in channel ID ' . Contao\Input::get('pid') . '.');
				}
				break;

			case 'cut':
			case 'copy':
				if (!in_array(Contao\Input::get('pid'), $root))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' newsletter recipient ID ' . $id . ' to channel ID ' . Contao\Input::get('pid') . '.');
				}
				// no break

			case 'edit':
			case 'show':
			case 'delete':
			case 'toggle':
				$objRecipient = $this->Database->prepare("SELECT pid FROM tl_newsletter_recipients WHERE id=?")
											   ->limit(1)
											   ->execute($id);

				if ($objRecipient->numRows < 1)
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Invalid newsletter recipient ID ' . $id . '.');
				}

				if (!in_array($objRecipient->pid, $root))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' recipient ID ' . $id . ' of newsletter channel ID ' . $objRecipient->pid . '.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
			case 'cutAll':
			case 'copyAll':
				if (!in_array($id, $root))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to access newsletter channel ID ' . $id . '.');
				}

				$objRecipient = $this->Database->prepare("SELECT id FROM tl_newsletter_recipients WHERE pid=?")
											 ->execute($id);

				/** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
				$objSession = Contao\System::getContainer()->get('session');

				$session = $objSession->all();
				$session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $objRecipient->fetchEach('id'));
				$objSession->replace($session);
				break;

			default:
				if (Contao\Input::get('act'))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Invalid command "' . Contao\Input::get('act') . '".');
				}

				if (!in_array($id, $root))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to access newsletter recipient ID ' . $id . '.');
				}
				break;
		}
	}

	/**
	 * Set the recipient status to "added manually" if they are moved to another channel
	 *
	 * @param Contao\DataContainer $dc
	 */
	public function clearOptInData(Contao\DataContainer $dc)
	{
		$this->Database->prepare("UPDATE tl_newsletter_recipients SET addedOn='' WHERE id=?")
					   ->execute($dc->id);
	}

	/**
	 * Check if recipients are unique per channel
	 *
	 * @param mixed                $varValue
	 * @param Contao\DataContainer $dc
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function checkUniqueRecipient($varValue, Contao\DataContainer $dc)
	{
		$objRecipient = $this->Database->prepare("SELECT COUNT(*) AS count FROM tl_newsletter_recipients WHERE email=? AND pid=(SELECT pid FROM tl_newsletter_recipients WHERE id=?) AND id!=?")
									   ->execute($varValue, $dc->id, $dc->id);

		if ($objRecipient->count > 0)
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], $GLOBALS['TL_LANG'][$dc->table][$dc->field][0]));
		}

		return $varValue;
	}

	/**
	 * Check if a recipient is blacklisted for a channel
	 *
	 * @param mixed                $varValue
	 * @param Contao\DataContainer $dc
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function checkBlacklistedRecipient($varValue, Contao\DataContainer $dc)
	{
		$objBlacklist = $this->Database->prepare("SELECT COUNT(*) AS count FROM tl_newsletter_blacklist WHERE hash=? AND pid=(SELECT pid FROM tl_newsletter_recipients WHERE id=?) AND id!=?")
									   ->execute(md5($varValue), $dc->id, $dc->id);

		if ($objBlacklist->count > 0)
		{
			throw new Exception($GLOBALS['TL_LANG']['ERR']['blacklisted']);
		}

		return $varValue;
	}

	/**
	 * List a recipient
	 *
	 * @param array $row
	 *
	 * @return string
	 */
	public function listRecipient($row)
	{
		$label = Contao\Idna::decodeEmail($row['email']);

		if ($row['addedOn'])
		{
			$label .= ' <span style="color:#999;padding-left:3px">(' . sprintf($GLOBALS['TL_LANG']['tl_newsletter_recipients']['subscribed'], Contao\Date::parse(Contao\Config::get('datimFormat'), $row['addedOn'])) . ')</span>';
		}
		else
		{
			$label .= ' <span style="color:#999;padding-left:3px">(' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['manually'] . ')</span>';
		}

		return sprintf('<div class="tl_content_left"><div class="list_icon" style="background-image:url(\'%ssystem/themes/%s/icons/%s.svg\')" data-icon="member.svg" data-icon-disabled="member_.svg">%s</div></div>', Contao\System::getContainer()->get('contao.assets.assets_context')->getStaticUrl(), Contao\Backend::getTheme(), ($row['active'] ? 'member' : 'member_'), $label) . "\n";
	}

	/**
	 * Return the "toggle visibility" button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
	{
		if (Contao\Input::get('tid'))
		{
			$this->toggleVisibility(Contao\Input::get('tid'), (Contao\Input::get('state') == 1), (func_num_args() <= 12 ? null : func_get_arg(12)));
			$this->redirect($this->getReferer());
		}

		// Check permissions AFTER checking the tid, so hacking attempts are logged
		if (!$this->User->hasAccess('tl_newsletter_recipients::active', 'alexf'))
		{
			return '';
		}

		$href .= '&amp;tid=' . $row['id'] . '&amp;state=' . ($row['active'] ? '' : 1);

		if (!$row['active'])
		{
			$icon = 'invisible.svg';
		}

		return '<a href="' . $this->addToUrl($href) . '" title="' . Contao\StringUtil::specialchars($title) . '"' . $attributes . '>' . Contao\Image::getHtml($icon, $label, 'data-state="' . ($row['active'] ? 1 : 0) . '"') . '</a> ';
	}

	/**
	 * Disable/enable a user group
	 *
	 * @param integer              $intId
	 * @param boolean              $blnVisible
	 * @param Contao\DataContainer $dc
	 *
	 * @throws Contao\CoreBundle\Exception\AccessDeniedException
	 */
	public function toggleVisibility($intId, $blnVisible, Contao\DataContainer $dc=null)
	{
		// Set the ID and action
		Contao\Input::setGet('id', $intId);
		Contao\Input::setGet('act', 'toggle');

		if ($dc)
		{
			$dc->id = $intId; // see #8043
		}

		// Trigger the onload_callback
		if (is_array($GLOBALS['TL_DCA']['tl_newsletter_recipients']['config']['onload_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_newsletter_recipients']['config']['onload_callback'] as $callback)
			{
				if (is_array($callback))
				{
					$this->import($callback[0]);
					$this->{$callback[0]}->{$callback[1]}($dc);
				}
				elseif (is_callable($callback))
				{
					$callback($dc);
				}
			}
		}

		// Check the field access
		if (!$this->User->hasAccess('tl_newsletter_recipients::active', 'alexf'))
		{
			throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to publish/unpublish newsletter recipient ID ' . $intId . '.');
		}

		$objRow = $this->Database->prepare("SELECT * FROM tl_newsletter_recipients WHERE id=?")
								 ->limit(1)
								 ->execute($intId);

		if ($objRow->numRows < 1)
		{
			throw new Contao\CoreBundle\Exception\AccessDeniedException('Invalid newsletter recipient ID ' . $intId . '.');
		}

		// Set the current record
		if ($dc)
		{
			$dc->activeRecord = $objRow;
		}

		$objVersions = new Contao\Versions('tl_newsletter_recipients', $intId);
		$objVersions->initialize();

		// Trigger the save_callback
		if (is_array($GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['active']['save_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['active']['save_callback'] as $callback)
			{
				if (is_array($callback))
				{
					$this->import($callback[0]);
					$blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, $dc);
				}
				elseif (is_callable($callback))
				{
					$blnVisible = $callback($blnVisible, $dc);
				}
			}
		}

		$time = time();

		// Update the database
		$this->Database->prepare("UPDATE tl_newsletter_recipients SET tstamp=$time, active='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
					   ->execute($intId);

		if ($dc)
		{
			$dc->activeRecord->tstamp = $time;
			$dc->activeRecord->active = ($blnVisible ? '1' : '');
		}

		// Trigger the onsubmit_callback
		if (is_array($GLOBALS['TL_DCA']['tl_newsletter_recipients']['config']['onsubmit_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_newsletter_recipients']['config']['onsubmit_callback'] as $callback)
			{
				if (is_array($callback))
				{
					$this->import($callback[0]);
					$this->{$callback[0]}->{$callback[1]}($dc);
				}
				elseif (is_callable($callback))
				{
					$callback($dc);
				}
			}
		}

		$objVersions->create();

		if ($dc)
		{
			$dc->invalidateCacheTags();
		}
	}
}
