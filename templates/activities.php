<? if ($enable): ?>
    <?= MessageBox::info(_('Die Aktivit�ts�bersicht kann nun als Feed abonniert werden.'),
                         array(_('�ber die URL des Feeds sind Inhalte (wie Forenbeitr�ge oder Wikiseiten) aus Stud.IP auch ohne Anmeldung abrufbar.'),
                               _('<b>Geben Sie daher diesen Link nicht an andere Personen weiter!</b>'))) ?>
<? endif ?>

<ul id="stream">
    <? foreach ($items as $item): ?>
        <li class="<?= $item['category'] ?><?= $item['author_id'] == $user ? ' self' : '' ?>">
            <span class="author">
                <?= Avatar::getAvatar($item['author_id'])->getImageTag(Avatar::MEDIUM) ?>
            </span>
            <div class="content">
                <span class="date">
                    <?= _('vor') ?> <?= $plugin->readableTime($item['updated']) ?>
                </span>
                <h2>
                    <a href="<?= $item['link'] ?>"><?= htmlReady($item['title']) ?></a>
                </h2>
                <div class="summary">
                    <?= htmlReady($item['summary']) ?>
                </div>
            </div>
        </li>
    <? endforeach ?>
</ul>

<?
$infobox_content = array(
    array(
        'kategorie' => _('Einstellungen:'),
        'eintrag'   => array(
            array(
                'icon' => $plugin->getPluginURL() . '/images/feed-icon-14x14.png',
                'text' => $this->render_partial('feed_enable')
            )
        )
    ), array(
        'kategorie' => _('Anzeigefilter:'),
        'eintrag'   => array(
            array(
                'icon' => 'suchen.gif',
                'text' => $this->render_partial('category_filter')
            )
        )
    ), array(
        'kategorie' => _('Hinweise:'),
        'eintrag'   => array(
            array(
                'icon' => 'ausruf_small.gif',
                'text' => sprintf(_('Auf dieser Seite k�nnen Sie die Aktivit�ten in Ihren Veranstaltungen und Einrichtungen in den letzten %d Tagen verfolgen.'), $days)
            ), array(
                'icon' => 'ausruf_small.gif',
                'text' => sprintf(_('�ber die URL des Feeds sind Inhalte (wie Forenbeitr�ge oder Wikiseiten) aus Stud.IP auch ohne Anmeldung abrufbar.
                                     <p><b>Geben Sie daher diesen Link nicht an andere Personen weiter!</b></p>'), $days)
            )
        )
    )
);

$infobox = array('picture' => 'infoboxes/online.jpg', 'content' => $infobox_content);
?>
