<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\NewsletterBundle\EventListener;

use Contao\CoreBundle\Event\SitemapEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Database;
use Contao\NewsletterChannelModel;
use Contao\NewsletterModel;
use Contao\PageModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener]
class SitemapListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly ContentUrlGenerator $urlGenerator,
    ) {
    }

    public function __invoke(SitemapEvent $event): void
    {
        $arrRoot = $this->framework->createInstance(Database::class)->getChildRecords($event->getRootPageIds(), 'tl_page');

        // Early return here in the unlikely case that there are no pages
        if (empty($arrRoot)) {
            return;
        }

        $arrPages = [];
        $time = time();

        // Get all calendars
        $objNewsletters = $this->framework->getAdapter(NewsletterChannelModel::class)->findAll();

        if (null === $objNewsletters) {
            return;
        }

        // Walk through each channel
        foreach ($objNewsletters as $objNewsletter) {
            if (!$objNewsletter->jumpTo) {
                continue;
            }

            // Skip channels outside the root nodes
            if (!empty($arrRoot) && !\in_array($objNewsletter->jumpTo, $arrRoot, true)) {
                continue;
            }

            $objParent = $this->framework->getAdapter(PageModel::class)->findWithDetails($objNewsletter->jumpTo);

            // The target page does not exist
            if (!$objParent) {
                continue;
            }

            // The target page has not been published (see #5520)
            if (!$objParent->published || ($objParent->start && $objParent->start > $time) || ($objParent->stop && $objParent->stop <= $time)) {
                continue;
            }

            // The target page is protected (see #8416)
            if ($objParent->protected && !$this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $objParent->groups)) {
                continue;
            }

            // The target page is exempt from the sitemap (see #6418)
            if ('noindex,nofollow' === $objParent->robots) {
                continue;
            }

            // Get the items
            $objItems = $this->framework->getAdapter(NewsletterModel::class)->findSentByPid($objNewsletter->id);

            if (null === $objItems) {
                continue;
            }

            foreach ($objItems as $objItem) {
                try {
                    $arrPages[] = $this->urlGenerator->generate($objItem, [], UrlGeneratorInterface::ABSOLUTE_URL);
                } catch (ExceptionInterface) {
                }
            }
        }

        foreach ($arrPages as $strUrl) {
            $event->addUrlToDefaultUrlSet($strUrl);
        }
    }
}
