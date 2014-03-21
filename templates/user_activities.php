<a name="user_activities"></a>

<? if ($my_profile): ?>
    <form style="float: right;" action="<?= URLHelper::getLink('#user_activities', array('as_public' => 0)) ?>" method="post">
        <label>
            <input type="checkbox" name="as_public" value="1" <?= $public ? 'checked' : '' ?> onchange="this.form.submit();">
            <?= _('Aktivitäten veröffentlichen') ?>
        </label>
        <noscript>
            <?= makeButton('speichern', 'input') ?>
        </noscript>
    </form>
<? endif ?>

<ul id="stream" style="clear: both;">
    <? foreach ($items as $item): ?>
        <li class="stream <?= $item['category'] ?><?= $item['author_id'] == $user ? ' self' : '' ?>">
            <span class="stream_author">
                <?= Avatar::getAvatar($item['author_id'])->getImageTag(Avatar::MEDIUM) ?>
            </span>
            <div class="stream_content">
                <span class="date">
                    <?= _('vor') ?> <?= $plugin->readableTime($item['updated']) ?>
                </span>
                <h2>
                    <a href="<?= $item['link'] ?>"><?= htmlReady($item['title']) ?></a>
                </h2>
                    <div class="summary">
                        <b><?= htmlReady($item['summary']) ?></b>
                        <br><br>
                        <div class="stream_details" onClick="$(this).toggleClass('expand')">
                            <?= $item['content'] ?: htmlReady($item['summary']) ?>
                        </div>
                    </div>
            </div>
        </li>
    <? endforeach ?>
</ul>
