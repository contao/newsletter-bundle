<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\NewsletterBundle\DataContainer;

use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * MemberDataContainer
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class MemberDataContainer
{
    /**
     * @var Connection
     */
    private $db;

    /**
     * Constructor.
     *
     * @param Connection $db
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Synchronize the newsletter subscriptions if the visibility is toggled
     *
     * @param bool          $isDisabled
     * @param DataContainer $dc
     *
     * @return bool
     */
    public function onToggleVisibility($isDisabled, DataContainer $dc)
    {
        if (!$dc->id) {
            return $isDisabled;
        }

        $email = (string) $this->db->fetchColumn('SELECT email FROM tl_member WHERE id=?', [$dc->id]);

        if ('' !== $email) {
            $this->db->update(
                'tl_newsletter_recipients',
                [
                    'tstamp' => time(),
                    'active' => $isDisabled ? '' : '1'
                ],
                ['email' => $email]
            );
        }

        return $isDisabled;
    }
}
