<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Symfony\Component\Routing\Exception\ExceptionInterface;

/**
 * Front end module "newsletter subscribe".
 *
 * @property string $nl_subscribe
 * @property array  $nl_channels
 * @property string $nl_template
 * @property string $nl_text
 * @property bool   $nl_hideChannels
 */
class ModuleSubscribe extends Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'nl_default';

	/**
	 * Display a wildcard in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['subscribe'][0] . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

			return $objTemplate->parse();
		}

		$this->nl_channels = StringUtil::deserialize($this->nl_channels);

		// Return if there are no channels
		if (empty($this->nl_channels) || !\is_array($this->nl_channels))
		{
			return '';
		}

		return parent::generate();
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		// Overwrite default template
		if ($this->nl_template)
		{
			$this->Template = new FrontendTemplate($this->nl_template);
			$this->Template->setData($this->arrData);
		}

		$this->Template->email = '';
		$this->Template->captcha = '';

		// Activate e-mail address
		if (str_starts_with(Input::get('token'), 'nl-'))
		{
			$this->activateRecipient();

			return;
		}

		$objWidget = null;

		// Set up the captcha widget
		if (!$this->disableCaptcha)
		{
			$arrField = array
			(
				'name' => 'subscribe_' . $this->id,
				'label' => $GLOBALS['TL_LANG']['MSC']['securityQuestion'],
				'inputType' => 'captcha',
				'eval' => array('mandatory'=>true)
			);

			$objWidget = new FormCaptcha(FormCaptcha::getAttributesFromDca($arrField, $arrField['name']));
		}

		$strFormId = 'tl_subscribe_' . $this->id;

		// Validate the form
		if (Input::post('FORM_SUBMIT') == $strFormId)
		{
			$varSubmitted = $this->validateForm($objWidget);

			if ($varSubmitted !== false)
			{
				$this->addRecipient(...$varSubmitted);
			}
		}

		// Add the captcha widget to the template
		if ($objWidget !== null)
		{
			$this->Template->captcha = $objWidget->parse();
		}

		$session = System::getContainer()->get('request_stack')->getSession();

		// Confirmation message
		if ($session->isStarted())
		{
			$flashBag = $session->getFlashBag();

			if ($flashBag->has('nl_confirm'))
			{
				$arrMessages = $flashBag->get('nl_confirm');

				$this->Template->mclass = 'confirm';
				$this->Template->message = $arrMessages[0];
			}
		}

		$arrChannels = array();
		$objChannel = NewsletterChannelModel::findByIds($this->nl_channels);

		// Get the titles
		if ($objChannel !== null)
		{
			while ($objChannel->next())
			{
				$arrChannels[$objChannel->id] = $objChannel->title;
			}
		}

		// Default template variables
		$this->Template->channels = $arrChannels;
		$this->Template->showChannels = !$this->nl_hideChannels;
		$this->Template->submit = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['subscribe']);
		$this->Template->channelsLabel = $GLOBALS['TL_LANG']['MSC']['nl_channels'];
		$this->Template->emailLabel = $GLOBALS['TL_LANG']['MSC']['emailAddress'];
		$this->Template->formId = $strFormId;
		$this->Template->id = $this->id;
		$this->Template->text = $this->nl_text;
	}

	/**
	 * Activate a recipient
	 */
	protected function activateRecipient()
	{
		$this->Template = new FrontendTemplate('mod_newsletter');

		$optIn = System::getContainer()->get('contao.opt_in');

		// Find an unconfirmed token
		if ((!$optInToken = $optIn->find(Input::get('token'))) || !$optInToken->isValid() || \count($arrRelated = $optInToken->getRelatedRecords()) < 1 || key($arrRelated) != 'tl_newsletter_recipients' || \count($arrIds = current($arrRelated)) < 1)
		{
			$this->Template->mclass = 'error';
			$this->Template->message = $GLOBALS['TL_LANG']['MSC']['invalidToken'];

			return;
		}

		if ($optInToken->isConfirmed())
		{
			$this->Template->mclass = 'error';
			$this->Template->message = $GLOBALS['TL_LANG']['MSC']['tokenConfirmed'];

			return;
		}

		$arrRecipients = array();

		// Validate the token
		foreach ($arrIds as $intId)
		{
			if (!$objRecipient = NewsletterRecipientsModel::findById($intId))
			{
				$this->Template->mclass = 'error';
				$this->Template->message = $GLOBALS['TL_LANG']['MSC']['invalidToken'];

				return;
			}

			if ($optInToken->getEmail() != $objRecipient->email)
			{
				$this->Template->mclass = 'error';
				$this->Template->message = $GLOBALS['TL_LANG']['MSC']['tokenEmailMismatch'];

				return;
			}

			$arrRecipients[] = $objRecipient;
		}

		$time = time();
		$arrAdd = array();
		$arrCids = array();

		// Activate the subscriptions
		foreach ($arrRecipients as $objRecipient)
		{
			$arrAdd[] = $objRecipient->id;
			$arrCids[] = $objRecipient->pid;

			$objRecipient->tstamp = $time;
			$objRecipient->active = true;
			$objRecipient->save();
		}

		$optInToken->confirm();

		// HOOK: post activation callback
		if (isset($GLOBALS['TL_HOOKS']['activateRecipient']) && \is_array($GLOBALS['TL_HOOKS']['activateRecipient']))
		{
			foreach ($GLOBALS['TL_HOOKS']['activateRecipient'] as $callback)
			{
				System::importStatic($callback[0])->{$callback[1]}($optInToken->getEmail(), $arrAdd, $arrCids, $this);
			}
		}

		// Confirm activation
		$this->Template->mclass = 'confirm';
		$this->Template->message = $GLOBALS['TL_LANG']['MSC']['nl_activate'];
	}

	/**
	 * Validate the subscription form
	 *
	 * @param Widget $objWidget
	 *
	 * @return array|bool
	 */
	protected function validateForm(Widget $objWidget=null)
	{
		// Validate the e-mail address
		$varInput = Idna::encodeEmail(Input::post('email', true));

		if (!Validator::isEmail($varInput))
		{
			$this->Template->mclass = 'error';
			$this->Template->message = $GLOBALS['TL_LANG']['ERR']['email'];

			return false;
		}

		$this->Template->email = $varInput;

		// Validate the channel selection
		$arrChannels = Input::post('channels');

		if (!\is_array($arrChannels))
		{
			$this->Template->mclass = 'error';
			$this->Template->message = $GLOBALS['TL_LANG']['ERR']['noChannels'];

			return false;
		}

		$arrChannels = array_intersect($arrChannels, $this->nl_channels); // see #3240

		if (empty($arrChannels))
		{
			$this->Template->mclass = 'error';
			$this->Template->message = $GLOBALS['TL_LANG']['ERR']['noChannels'];

			return false;
		}

		$this->Template->selectedChannels = $arrChannels;

		// Check if there are any new subscriptions
		$arrSubscriptions = array();

		if (($objSubscription = NewsletterRecipientsModel::findBy(array("email=? AND active=1"), $varInput)) !== null)
		{
			$arrSubscriptions = $objSubscription->fetchEach('pid');
		}

		$arrChannels = array_diff($arrChannels, $arrSubscriptions);

		if (empty($arrChannels))
		{
			$this->Template->mclass = 'error';
			$this->Template->message = $GLOBALS['TL_LANG']['ERR']['subscribed'];

			return false;
		}

		// Validate the captcha
		if ($objWidget !== null)
		{
			$objWidget->validate();

			if ($objWidget->hasErrors())
			{
				return false;
			}
		}

		return array($varInput, $arrChannels);
	}

	/**
	 * Add a new recipient
	 *
	 * @param string $strEmail
	 * @param array  $arrNew
	 */
	protected function addRecipient($strEmail, $arrNew)
	{
		// Remove old subscriptions that have not been activated yet
		if (($objOld = NewsletterRecipientsModel::findOldSubscriptionsByEmailAndPids($strEmail, $arrNew)) !== null)
		{
			while ($objOld->next())
			{
				$objOld->delete();
			}
		}

		$time = time();
		$arrRelated = array();

		// Add the new subscriptions
		foreach ($arrNew as $id)
		{
			$objRecipient = new NewsletterRecipientsModel();
			$objRecipient->pid = $id;
			$objRecipient->tstamp = $time;
			$objRecipient->email = $strEmail;
			$objRecipient->active = false;
			$objRecipient->addedOn = $time;
			$objRecipient->save();

			// Remove the deny list entry (see #4999)
			if (($objDenyList = NewsletterDenyListModel::findByHashAndPid(md5($strEmail), $id)) !== null)
			{
				$objDenyList->delete();
			}

			$arrRelated['tl_newsletter_recipients'][] = $objRecipient->id;
		}

		$optIn = System::getContainer()->get('contao.opt_in');
		$optInToken = $optIn->create('nl', $strEmail, $arrRelated);

		// Get the channels
		$objChannel = NewsletterChannelModel::findByIds($arrNew);

		// Prepare the simple token data
		$arrData = array();
		$arrData['token'] = $optInToken->getIdentifier();
		$arrData['domain'] = Idna::decode(Environment::get('host'));
		$arrData['link'] = Idna::decode(Environment::get('url')) . Environment::get('requestUri') . ((str_contains(Environment::get('requestUri'), '?')) ? '&' : '?') . 'token=' . $optInToken->getIdentifier();
		$arrData['channel'] = $arrData['channels'] = implode("\n", $objChannel->fetchEach('title'));

		// Send the token
		$optInToken->send(
			sprintf($GLOBALS['TL_LANG']['MSC']['nl_subject'], Idna::decode(Environment::get('host'))),
			System::getContainer()->get('contao.string.simple_token_parser')->parse($this->nl_subscribe, $arrData)
		);

		// Redirect to the jumpTo page
		if ($objTarget = PageModel::findById($this->objModel->jumpTo))
		{
			try
			{
				$this->redirect(System::getContainer()->get('contao.routing.content_url_generator')->generate($objTarget));
			}
			catch (ExceptionInterface)
			{
				// Ignore if target URL cannot be generated and reload the page
			}
		}

		System::getContainer()->get('request_stack')->getSession()->getFlashBag()->set('nl_confirm', $GLOBALS['TL_LANG']['MSC']['nl_confirm']);

		$this->reload();
	}
}
