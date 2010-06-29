<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

<feed xmlns="http://www.w3.org/2005/Atom">
    <title>
        <?= htmlspecialchars(studip_utf8encode($title)) ?>
    </title>
    <subtitle><?= 'ActivityFeed' ?></subtitle>
    <link href="<?= htmlspecialchars($GLOBALS['ABSOLUTE_URI_STUDIP']) ?>"/>
    <author>
        <name>
            <?= htmlspecialchars(utf8_encode($GLOBALS['UNI_NAME_CLEAN'])) ?>
        </name>
        <email>
            <?= htmlspecialchars($GLOBALS['UNI_CONTACT']) ?>
        </email>
    </author>

    <? foreach ($items as $item): ?>
        <entry>
            <id>urn:studip:<?= $item['id'] ?></id>
            <title>
                <?= htmlspecialchars(studip_utf8encode($item['title'])) ?>
            </title>
            <author>
                <name>
                    <?= htmlspecialchars(studip_utf8encode($item['author'])) ?>
                </name>
            </author>
            <link href="<?= $item['link'] ?>"/>
            <updated><?= date('c', $item['updated']) ?></updated>
            <summary type="html">
                <?= htmlspecialchars(studip_utf8encode($item['summary'])) ?>
            </summary>
            <content type="html">
                <? if ($item['content'] != ''): ?>
                    <?= htmlspecialchars(utf8_encode(formatReady($item['content']))) ?>
                <? else: ?>
                    <?= htmlspecialchars(studip_utf8encode($item['summary'])) ?>
                <? endif ?>
            </content>
            <category term="<?= $item['category'] ?>"/>
        </entry>
    <? endforeach ?>
</feed>
