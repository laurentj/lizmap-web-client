<?php
/**
 *
 * @package   lizmap
 * @subpackage view
 * @author    3liz
 * @copyright 2017 3liz
 * @link      http://3liz.com
 * @license    Mozilla Public License : http://www.mozilla.org/MPL/
 */

class legacyCtrl extends jController
{
    protected function redirectTo($action) {
        $rep = $this->getResponse('redirect');
        $rep->temporary = false;
        $rep->action = $action;
        $rep->params = $this->params();
        unset($rep->params['module']);
        unset($rep->params['action']);
        return $rep;
    }

    function defaultIndex() {
        return $this->redirectTo('view~default:index');
    }

    function appMetadata() {
        return $this->redirectTo('view~app:metadata');
    }

    function mapIndex() {

        // the new action require a repository and a project
        // so if we don't have one, we take the default.
        $rep = $this->getResponse('redirect');
        $rep->temporary = false;
        $rep->action = 'view~map:index';
        $rep->params = $this->params();
        unset($rep->params['module']);
        unset($rep->params['action']);

        $repository = $this->param('repository');
        $project = $this->param('project');

        if (!$project && !$repository) {
            return $rep;
        }

        $rep->action = 'view~map:project';
        $lser = lizmap::getServices();

        if (!$repository) {
            $rep->params['repository'] = $lser->defaultRepository;
        }

        $lrep = lizmap::getRepository($repository);
        if (!$project) {
            try {
                $lproj = lizmap::getProject($lrep->getKey().'~'.$lser->defaultProject);
                if (!$lproj) {
                    jMessage::add('The parameter project is mandatory!', 'error');
                    $rep->action = 'view~default:index';
                    $rep->params = array();
                    return $rep;
                }
                $rep->params['project'] = $lser->defaultProject;
            }
            catch(UnknownLizmapProjectException $e) {
                jMessage::add('The parameter project is mandatory!', 'error');
                return $rep;
            }
        }

        return $rep;
    }

    function mediaGetMedia() {
        return $this->redirectTo('view~media:getMedia');
    }

    function mediaIllustration() {
        return $this->redirectTo('view~media:illustration');
    }

    function mediaGetCssFile() {
        return $this->redirectTo('view~media:getCssFile');
    }

    function mediaLogo() {
        return $this->redirectTo('view~media:logo');
    }

    function mediaGetDefaultTheme() {
        return $this->redirectTo('view~media:getDefaultTheme');
    }

    function translateIndex() {
        return $this->redirectTo('view~translate:index');
    }

    function translateGetDictionary() {
        return $this->redirectTo('view~translate:getDictionary');
    }

    function ajaxIndex() {
        return $this->redirectTo('view~ajax:index');
    }

    function ajaxMap() {
        return $this->redirectTo('view~ajax:map');
    }
}