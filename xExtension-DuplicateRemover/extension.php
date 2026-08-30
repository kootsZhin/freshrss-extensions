<?php

/**
 * DuplicateRemover extension for FreshRSS.
 */
class DuplicateRemoverExtension extends Minz_Extension {
    private $dedupe_mode = 'title';
    private $enable_log = false;

    public function init() {
        $this->loadConfiguration();
        $this->registerHook('entry_before_add', [$this, 'checkDuplicate']);
        $this->registerHook('freshrss_user_maintenance', [$this, 'cleanDuplicates']);

        if ($this->enable_log) {
            error_log('DuplicateRemover: Extension initialized, mode=' . $this->dedupe_mode);
        }
    }

    /**
     * Load user configuration, including older top-level configuration keys.
     */
    private function loadConfiguration() {
        $mode = $this->getUserConfigurationValue('mode');
        if ($mode === null && isset(FreshRSS_Context::$user_conf->DuplicateRemover_mode)) {
            $mode = FreshRSS_Context::$user_conf->DuplicateRemover_mode;
        }
        $this->dedupe_mode = in_array($mode, ['title', 'title_link'], true) ? $mode : 'title';

        $log = $this->getUserConfigurationValue('enable_log');
        if ($log === null && isset(FreshRSS_Context::$user_conf->DuplicateRemover_log)) {
            $log = FreshRSS_Context::$user_conf->DuplicateRemover_log;
        }
        $this->enable_log = ($log === true || $log === '1' || $log === 1);
    }

    /**
     * Check whether an entry duplicates an article already present in another feed.
     *
     * Returning null cancels insertion of the new article.
     *
     * @param FreshRSS_Entry $entry Entry about to be inserted.
     * @return FreshRSS_Entry|null
     */
    public function checkDuplicate($entry) {
        try {
            if (!($entry instanceof FreshRSS_Entry)) {
                return $entry;
            }

            $title = trim($entry->title());
            if ($title === '') {
                return $entry;
            }

            $link = $entry->link(true);
            if ($this->dedupe_mode === 'title_link' && $link === '') {
                return $entry;
            }

            $feedId = (int)$entry->feedId();
            $entryDAO = FreshRSS_Factory::createEntryDao();

            // FreshRSS buffers new entries in `_entrytmp` during a batch refresh
            // and only commits them to `_entry` after all feeds have been processed.
            // Check both tables so a cross-feed duplicate is caught even when both
            // feeds are refreshed in the same run.
            foreach (['_entry', '_entrytmp'] as $table) {
                if ($this->dedupe_mode === 'title_link') {
                    $sql = 'SELECT 1 FROM `' . $table . '` '
                        . 'WHERE TRIM(title) = :title AND link = :link AND id_feed <> :id_feed LIMIT 1';
                    $values = [
                        ':title' => $title,
                        ':link' => $link,
                        ':id_feed' => $feedId,
                    ];
                } else {
                    $sql = 'SELECT 1 FROM `' . $table . '` '
                        . 'WHERE TRIM(title) = :title AND id_feed <> :id_feed LIMIT 1';
                    $values = [
                        ':title' => $title,
                        ':id_feed' => $feedId,
                    ];
                }

                $rows = $entryDAO->fetchAssoc($sql, $values);
                if (!empty($rows)) {
                    if ($this->enable_log) {
                        $mode_str = $this->dedupe_mode === 'title_link' ? 'title+link' : 'title';
                        error_log("DuplicateRemover: Skipped duplicate - title: {$title}, mode: {$mode_str}");
                    }
                    return null;
                }
            }
        } catch (Exception $e) {
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        } catch (Error $e) {
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        }

        return $entry;
    }

    /**
     * Mark duplicate articles that are already in the database as read,
     * keeping the earliest copy unread. Only cross-feed duplicate groups are
     * considered.
     */
    public function cleanDuplicates() {
        try {
            $entryDAO = FreshRSS_Factory::createEntryDao();

            if ($this->dedupe_mode === 'title_link') {
                $groups = $entryDAO->fetchAssoc(
                    'SELECT TRIM(title) AS title, link FROM `_entry` '
                    . 'GROUP BY TRIM(title), link HAVING COUNT(DISTINCT id_feed) > 1'
                );
            } else {
                $groups = $entryDAO->fetchAssoc(
                    'SELECT TRIM(title) AS title FROM `_entry` '
                    . 'GROUP BY TRIM(title) HAVING COUNT(DISTINCT id_feed) > 1'
                );
            }

            if (empty($groups)) {
                return;
            }

            $idsToMarkRead = [];

            foreach ($groups as $group) {
                $title = isset($group['title']) ? trim((string)$group['title']) : '';
                if ($title === '') {
                    continue;
                }

                if ($this->dedupe_mode === 'title_link') {
                    $link = isset($group['link']) ? (string)$group['link'] : '';
                    $rows = $entryDAO->fetchAssoc(
                        'SELECT id FROM `_entry` '
                        . 'WHERE TRIM(title) = :title AND link = :link ORDER BY date ASC, id ASC',
                        [
                            ':title' => $title,
                            ':link' => $link,
                        ]
                    );
                } else {
                    $rows = $entryDAO->fetchAssoc(
                        'SELECT id FROM `_entry` WHERE TRIM(title) = :title ORDER BY date ASC, id ASC',
                        [':title' => $title]
                    );
                }

                if (empty($rows) || count($rows) < 2) {
                    continue;
                }

                $ids = [];
                foreach ($rows as $row) {
                    if (!empty($row['id'])) {
                        $ids[] = (string)$row['id'];
                    }
                }

                if (count($ids) < 2) {
                    continue;
                }

                array_shift($ids);
                $idsToMarkRead = array_merge($idsToMarkRead, $ids);
            }

            if (!empty($idsToMarkRead)) {
                $entryDAO->markRead($idsToMarkRead, true);

                if ($this->enable_log) {
                    error_log('DuplicateRemover: Marked ' . count($idsToMarkRead) . ' existing duplicate article(s) as read');
                }
            }
        } catch (Exception $e) {
            error_log('DuplicateRemover: Error cleaning duplicates - ' . $e->getMessage());
        } catch (Error $e) {
            error_log('DuplicateRemover: Error cleaning duplicates - ' . $e->getMessage());
        }
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $mode = Minz_Request::param('dedupe_mode', 'title');
            if (!in_array($mode, ['title', 'title_link'], true)) {
                $mode = 'title';
            }

            $this->dedupe_mode = $mode;
            $this->enable_log = Minz_Request::param('enable_log', '') === '1';
            $this->setUserConfiguration([
                'mode' => $mode,
                'enable_log' => $this->enable_log,
            ]);
        }
    }

    /**
     * Remove extension configuration.
     */
    public function handleUninstallAction() {
        $this->removeUserConfiguration();
    }
}
