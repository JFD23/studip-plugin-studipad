<?php

class PadsController extends StudipController
{
    public function __construct($dispatcher)
    {
        parent::__construct($dispatcher);
        $this->plugin = $dispatcher->plugin;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (!\Context::getId()) {
            throw new \AccessDeniedException();
        }

        $this->flash = Trails_Flash::instance();
        $this->set_layout(
            $GLOBALS['template_factory']->open(\Request::isXhr() ? 'layouts/dialog' : 'layouts/base')
        );
        $this->setDefaultPageTitle();

        if (!$this->client = $this->plugin->getClient()) {
            $action = 'setuperror';
        }
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function index_action()
    {
        \PageLayout::setHelpKeyword('Basis.StudiPad');
        if (\Navigation::hasItem('/course/studipad/index')) {
            \Navigation::activateItem('/course/studipad/index');
        }
        \PageLayout::addStylesheet($this->plugin->getPluginURL().'/stylesheets/studipad.css');

        $cid = \Context::getId();
        $this->newPadName = '';
        $this->padadmin = $GLOBALS['perm']->have_studip_perm('tutor', $cid);

        $eplGroupId = $this->requireGroup();

        try {
            $grouppads = $this->client->listPads($eplGroupId);
            $pads = $grouppads->padIDs;

            if (!count($pads)) {
                $this->message = dgettext(
                    'studipad',
                    'Zur Zeit sind keine Stud.IPads für diese Veranstaltung vorhanden.'
                );
            }

            $this->tpads = $this->getPads($cid, $eplGroupId, $pads);
        } catch (Exception $ex) {
            $this->error = '7:'.$ex->getMessage();
        }
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function setuperror_action()
    {
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function export_pdf_action($pad)
    {
        $exportFn = function ($padCallId) {
            return \Config::get()->getValue('STUDIPAD_PADBASEURL').'/'.$padCallId.'/export/pdf';
        };
        $this->redirectToEtherpad($pad, $exportFn);
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function open_action($pad)
    {
        $eplGroupId = $this->requireGroup();
        $padCallId = $eplGroupId.'$'.$pad;
        $url = $this->redirectToEtherpad($pad).'&studip=true'.$this->getHtmlControlString($padCallId);
        var_dump($url);
        $this->redirect($url);
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function settings_action($padid)
    {
        $this->requireTutor();

        $eplGroupId = $this->requireGroup();

        try {
            $grouppads = $this->client->listPads($eplGroupId);
            $pads = $grouppads->padIDs;
            $tpads = $this->getPads(\Context::getId(), $eplGroupId, $pads);
        } catch (\Exception $e) {
            $this->flash['error'] = $e->getMessage();

            return $this->redirect('');
        }

        if (!isset($tpads[$padid])) {
            $this->flash['error'] = 'Dieses Pad konnte nicht gefunden werden.';

            return $this->redirect('');
        }

        $this->padid = $padid;
        $this->pad = $tpads[$padid];
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function store_settings_action($pad)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();
        $padid = $eplGroupId.'$'.$pad;

        $controls = [];
        foreach (self::getControlsKeys() as $key) {
            $controls[$key] = \Request::get($key) ? 1 : 0;
        }
        $this->setControls($padid, $controls);

        $this->flash['message'] = dgettext('studipad', 'Einstellungen gespeichert.');
        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function add_password_action($padid)
    {
        $this->requireTutor();
        $this->padid = $padid;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function store_password_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        try {
            $padpassword = \Request::get('pad_password');
            $this->client->setPassword($eplGroupId.'$'.$padid, $padpassword);
            $this->flash['message'] = dgettext('studipad', 'Passwort gesetzt.');
        } catch (Exception $e) {
            $this->flash['error'] = dgettext('studipad', 'Das Passwort des Pads konnte nicht gesetzt werden.');
        }

        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function remove_password_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        try {
            $this->client->setPassword($eplGroupId.'$'.$padid, null);
            $this->flash['message'] = dgettext('studipad', 'Passwort entfernt.');
        } catch (Exception $e) {
            $this->flash['error'] = dgettext('studipad', 'Das Passwort des Pads konnte nicht entfernt werden.');
        }

        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function activate_write_protect_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        $this->setWriteProtection($eplGroupId.'$'.$padid, 1);
        $this->flash['message'] = dgettext('studipad', 'Schreibschutz aktiviert.');
        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function deactivate_write_protect_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        $this->setWriteProtection($eplGroupId.'$'.$padid, 0);
        $this->flash['message'] = dgettext('studipad', 'Schreibschutz deaktiviert.');
        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function publish_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        try {
            $this->client->setPublicStatus($eplGroupId.'$'.$padid, 'true');
            $this->flash['message'] = dgettext('studipad', 'Veröffentlicht.');
        } catch (Exception $e) {
            $this->flash['error'] = dgettext('studipad', 'Pad konnte nicht veröffentlicht werden.');
        }

        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function unpublish_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        try {
            $this->client->setPublicStatus($eplGroupId.'$'.$padid, 'false');
            $this->flash['message'] = dgettext('studipad', 'Veröffentlichung aufgehoben.');
        } catch (Exception $e) {
            $this->flash['error'] = dgettext('studipad', 'Veröffentlichung des Pads konnte nicht aufgehoben werden.');
        }

        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function create_action()
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        $name = trim(\Request::get('new_pad_name', ''));
        if ('' === $name || mb_strlen($name) > 32) {
            $this->flash['error'] = dgettext(
                'studipad',
                'Es muss ein Name angegeben werden der aus maximal 32 Zeichen besteht.'
            );
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            $this->flash['error'] = dgettext(
                'studipad',
                'Namen neuer Pads dürfen nur aus Buchstaben, Zahlen, Binde- und Unterstrichen bestehen.'
            );
        } else {
            try {
                $result = $this->client->createGroupPad($eplGroupId, $name, \Config::get()->getValue('STUDIPAD_INITEXT'));
                $this->createControls($result->padID);
                $this->flash['message'] = dgettext('studipad', 'Das Pad wurde erfolgreich angelegt.');
            } catch (\Exception $e) {
                $this->flash['error'] = dgettext('studipad', 'Das Pad konnte nicht angelegt werden.');
            }
        }

        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function delete_action($padid)
    {
        $this->requireTutor();
        $eplGroupId = $this->requireGroup();

        try {
            $this->client->deletePad($eplGroupId.'$'.$padid);
            $this->flash['message'] = dgettext('studipad', 'Das Pad wurde gelöscht.');
        } catch (Exception $e) {
            $this->flash['error'] = dgettext('studipad', 'Das Pad konnte nicht gelöscht werden.');
        }

        $this->redirect('');
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function snapshot_action($padid)
    {
        $this->requireTutor();

        if (!$taskId = \CronjobTask::findOneByFilename(\StudIPadPlugin::SNAPSHOTTER)->task_id) {
            $this->flash['error'] = dgettext('studipad', 'Es konnte kein Snapshot des Pads erstellt werden.');
        } else {
            \CronjobScheduler::scheduleOnce(
                $taskId,
                strtotime('+5 seconds'),
                \CronjobSchedule::PRIORITY_NORMAL,
                [
                    'userid' => $this->getCurrentUser()->id,
                    'cid' => \Context::getId(),
                    'pad' => $padid,
                ]
            )->activate();

            $this->flash['message'] = dgettext(
                'studipad',
                'Der aktuelle Inhalt des Pads wird in einigen Sekunden gesichert.'
            );
        }

        $this->redirect('');
    }

    protected function getPads($cid, $eplGroupId, $pads)
    {
        $tpads = [];
        if (count($pads)) {
            foreach ($pads as $pval) {
                $padparts = explode('$', $pval);
                $pad = $padparts[1];
                $tpads[$pad] = [];

                $padid = $eplGroupId.'$'.$pad;

                if (!strlen($tpads[$pad]['title'])) {
                    $tpads[$pad]['title'] = $pad;
                }

                $getPublicStatus = $this->client->getPublicStatus($padid);
                $tpads[$pad]['public'] = isset($getPublicStatus) ? $getPublicStatus->publicStatus : false;

                $isPasswordProtected = $this->client->isPasswordProtected($padid);
                $tpads[$pad]['hasPassword'] = isset($isPasswordProtected)
                                            ? $isPasswordProtected->isPasswordProtected
                                            : false;

                $tpads[$pad]['readOnly'] = $this->isWriteProtected($padid);

                $tpads[$pad] = array_merge($tpads[$pad], $this->getControls($padid));

                $lastVisit = object_get_visit($cid, 'sem', 'last');
                $clientLastEdited = $this->client->getLastEdited($padid);
                $padLastEdited = floor($clientLastEdited->lastEdited / 1000);
                $tpads[$pad]['new'] = $padLastEdited > $lastVisit;
                $tpads[$pad]['lastEdited'] = $padLastEdited;
            }
        }

        return $tpads;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function getCurrentUser()
    {
        $currentUser = \User::findCurrent();

        return $currentUser;
    }

    protected function setDefaultPageTitle()
    {
        \PageLayout::setTitle(Context::getHeaderLine().' - StudIPad');
    }

    //////// OLD STUFF

    protected function createControls($padid)
    {
        $stmt = \DBManager::get()->prepare(
            'INSERT INTO plugin_StudIPad_controls '.
            '(pad_id, controls, readonly) VALUES (?, ?, ?)'
        );
        $stmt->execute([$padid, self::getControlsDefaultString(), 0]);
    }

    protected function getControlSet($padid, $control)
    {
        $db = \DBManager::get();

        switch ($control) {
            case 'showControls':
                $id = '0';
                break;
            case 'showColorBlock':
                $id = '1';
                break;
            case 'showImportExportBlock':
                $id = '2';
                break;
            case 'showChat':
                $id = '3';
                break;
            case 'showLineNumbers':
                $id = '4';
                break;
        }

        $sql = "SELECT controls FROM plugin_StudIPad_controls WHERE pad_id = '$padid'";

        $result = $db->query($sql)->fetchColumn();
        $setting = explode(';', $result);

        return $setting[$id];
    }

    private static function getControlsKeys()
    {
        return ['showControls', 'showColorBlock', 'showImportExportBlock', 'showChat', 'showLineNumbers'];
    }

    private static function getControlsDefaultValue()
    {
        return \Config::get()->getValue('STUDIPAD_CONTROLS_DEFAULT') ? 1 : 0;
    }

    private static function getControlsDefaultString()
    {
        return join(';', array_fill(0, count(self::getControlsKeys()), self::getControlsDefaultValue()));
    }

    private function getControls($padid)
    {
        $stmt = \DBManager::get()->prepare('SELECT controls FROM plugin_StudIPad_controls WHERE pad_id = ? LIMIT 1');
        $stmt->execute([$padid]);

        $controls = $stmt->fetch(PDO::FETCH_COLUMN);
        if (false === $controls) {
            $controls = self::getControlsDefaultString();
        }

        return array_combine(self::getControlsKeys(), explode(';', $controls));
    }

    private function setControls($padid, $controls)
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE plugin_StudIPad_controls SET controls = ? WHERE pad_id = ?'
        );

        $defaultValue = self::getControlsDefaultValue();
        $controlsString = join(';', array_map(function ($key) use ($controls, $defaultValue) {
            return isset($controls[$key]) ? ($controls[$key] ? 1 : 0) : $defaultValue;
        }, self::getControlsKeys()));

        $stmt->execute([$controlsString, $padid]);
    }

    private function isWriteProtected($padid)
    {
        $stmt = \DBManager::get()->prepare('SELECT readonly FROM plugin_StudIPad_controls WHERE pad_id = ? LIMIT 1');
        $stmt->execute([$padid]);

        return (bool) $stmt->fetch(PDO::FETCH_COLUMN);
    }

    private function setWriteProtection($padid, $protect)
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE plugin_StudIPad_controls SET readonly = ? WHERE pad_id = ?'
        );

        $stmt->execute([$protect ? 1 : 0, $padid]);
    }

    protected function setControlSet($padid, $padname, $controlset, $readonly)
    {
        $result = \DBManager::get()->prepare('REPLACE INTO plugin_StudIPad_controls (pad_id, controls, readonly) VALUES (:pid, :controls, :readonly)');
        $control = $result->execute(array('pid' => $padid, 'controls' => $controlset, 'readonly' => $readonly));

        return $control
            ? sprintf(dgettext('studipad', 'Die Einstellungen für das Pad "%s" wurden gespeichert!'), $padname)
            : sprintf(dgettext('studipad', 'Die Einstellungen für das Pad "%s" konnten nicht gespeichert werden!'), $padname);
    }

    protected function getHtmlControlString($padid)
    {
        $controls = $this->getControls($padid);
        $result = '&showControls='.($controls['showControls'] ? 'true' : 'false');

        if ($controls['showControls']) {
            foreach (['showColorBlock', 'showImportExportBlock', 'showChat', 'showLineNumbers'] as $key) {
                $result .= sprintf('&%s=%s', $key, $controls[$key] ? 'true' : 'false');
            }
        }

        return $result;
    }

    protected function getReadOnlyId($padid)
    {
        try {
            $padRO = $this->client->getReadOnlyID($padid);
            $result[0] = true;
        } catch (Excepion $e) {
            $result[0] = false;
            $result[2] = $e->getMessage();
        }

        $result[1] = $padRO->readOnlyID;

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function url_for($to = '')
    {
        $args = func_get_args();

        // find params
        $params = [];
        if (is_array(end($args))) {
            $params = array_pop($args);
        }

        // urlencode all but the first argument
        $args = array_map('urlencode', $args);
        $args[0] = $to;

        return \PluginEngine::getURL($this->dispatcher->plugin, $params, implode('/', $args));
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function requireTutor()
    {
        $cid = \Context::getId();
        if (!$GLOBALS['perm']->have_studip_perm('tutor', $cid)) {
            throw new \AccessDeniedException();
        }
    }

    protected function requireGroup()
    {
        $cid = \Context::getId();
        try {
            $eplGmap = $this->client->createGroupIfNotExistsFor('subdomain:'.$cid);
        } catch (\Exception $e) {
            throw new \Trails_Exception(500, $e->getMessage());
        }

        if (!$eplGroupId = $eplGmap->groupID) {
            throw new \Trails_Exception(500, dgettext('studipad', 'Es ist ein Verbindungsfehler aufgetreten!'));
        }

        return $eplGroupId;
    }

    protected function requirePad($pad)
    {
        if (!preg_match('|^[A-Za-z0-9_-]+$|i', $pad)) {
            throw new \Trails_Exception(400, dgettext('studipad', 'Dieses Pad existiert nicht.'));
        }

        return $pad;
    }

    protected function getPadCallId($eplGroupId, $pad)
    {
        if (!$this->isWriteProtected($eplGroupId.'$'.$pad)) {
            return $eplGroupId.'$'.$pad;
        }
        list($success, $padCallId, $error) = $this->getReadOnlyId($eplGroupId.'$'.$pad);
        if (!$success) {
            throw new \Trails_Exception(
                sprintf(
                    dgettext('studipad', 'Fehler beim Ermitteln der padCallId: %s'),
                    $error
                )
            );
        }

        return $padCallId;
    }

    protected function redirectToEtherpad($pad)
    {
        $eplGroupId = $this->requireGroup();

        if (!preg_match('|^[A-Za-z0-9_-]+$|i', $pad)) {
            $this->flash['error'] = 'Dieses Pad existiert nicht.';

            return $this->redirect('');
        }

        $user = $this->getCurrentUser();
        $author = $this->client->createAuthorIfNotExistsFor($user->id, $user->getFullName());
        $authorID = $author->authorID;

        $until = strtotime('tomorrow');
        $eplSid = $this->client->createSession($eplGroupId, $authorID, $until);

        return sprintf(
            '%s/auth_session?sessionID=%s&padName=%s',
            dirname(Config::get()->getValue('STUDIPAD_PADBASEURL')),
            $eplSid->sessionID,
            $this->getPadCallId($eplGroupId, $pad)
        );
    }
}