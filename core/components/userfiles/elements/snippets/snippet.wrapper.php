<?php

/** @var array $scriptProperties */
/** @var userfiles $userfiles */
$fqn = $modx->getOption('userfiles_class', null, 'userfiles.userfiles', true);
$path = $modx->getOption('userfiles_class_path', null,
    $modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/userfiles/');
if (!$userfiles = $modx->getService($fqn, '', $path . 'model/',
    array('core_path' => $path))
) {
    return false;
}

$tpl = $scriptProperties['tpl'] = $modx->getOption('tpl', $scriptProperties, '', true);
$parents = $scriptProperties['parents'] = $modx->getOption('parents', $scriptProperties, 0, true);
$resources = $scriptProperties['resources'] = $modx->getOption('resources', $scriptProperties, '', true);
$class = $scriptProperties['class'] = $modx->getOption('class', $scriptProperties, 'modResource', true);
$includeFilesThumbs = $scriptProperties['includeFilesThumbs'] = $modx->getOption('includeFilesThumbs',
    $scriptProperties, 0, true);
$includeFilesThumbs = $userfiles->explodeAndClean($includeFilesThumbs);
$includeAllFiles = (bool)$modx->getOption('includeAllFiles', $scriptProperties, 0, true);

$element = $scriptProperties['element'] = $modx->getOption('element', $scriptProperties, 'pdoResources', true);
if (isset($this) AND $this instanceof modSnippet AND $element == $this->get('name')) {
    $properties = $this->getProperties();
    $element = $scriptProperties['element'] = $modx->getOption('element', $properties, 'pdoResources', true);
}

$where = array();
$leftJoin = array();
$innerJoin = array();
$leftJoinFiles = array();
$innerJoinFiles = array();
$select = array(
    $class => "{$class}.*"
);

$groupby = array(
    "{$class}.id",
);

// Add user parameters
foreach (array('where', 'leftJoin', 'innerJoin', 'select', 'groupby') as $v) {
    if (!empty($scriptProperties[$v])) {
        $tmp = $scriptProperties[$v];
        if (!is_array($tmp)) {
            $tmp = json_decode($tmp, true);
        }
        if (!is_array($tmp)) {
            $tmp = array($scriptProperties[$v]);
        }
        if (is_array($tmp)) {
            $$v = array_merge($$v, $tmp);
        }
    }
    unset($scriptProperties[$v]);
}

// join Files
foreach (array('leftJoinFiles' => 'leftJoin', 'innerJoinFiles' => 'innerJoin') as $k => $v) {
    if (!empty($scriptProperties[$k])) {
        ${$k} = $userfiles->explodeAndClean($scriptProperties[$k]);
        foreach (${$k} as $var) {
            $tmp = $userfiles->explodeAndClean($var, ':');

            $list = array_shift($tmp);
            if (empty($tmp)) {
                $tmp = array($class);
            }

            $where[$list . '.class:IN'] = $tmp;
            ${$v}[$list] = array(
                'class' => 'UserFile',
                'on'    => "`{$list}`.parent = `{$class}`.id AND `{$list}`.list = '{$list}'",
            );

            $select[$list][] = $modx->getSelectColumns('UserFile', $list, $list . '_');
            if ($includeAllFiles) {
                $select[$list][] = "GROUP_CONCAT(`{$list}`.`url` SEPARATOR ',') as `{$list}`";
            }
            foreach ($includeFilesThumbs as $thumb) {
                $size = explode('x', $thumb);
                $sizeLike = array();
                if (!empty($size[0])) {
                    $sizeLike[] = 'w\":' . $size[0];
                }
                if (!empty($size[1])) {
                    $sizeLike[] = '"\h\":' . $size[1];
                }
                $sizeLike = implode(',', $sizeLike);

                ${$v}[$thumb] = array(
                    'class' => 'UserFile',
                    'on'    => "`{$thumb}`.class = 'UserFile' AND `{$thumb}`.parent = `{$list}`.id AND `{$thumb}`.list = '{$list}' AND `{$thumb}`.properties LIKE '%{$sizeLike}%'",
                );
                $select[$thumb][] = $modx->getSelectColumns('UserFile', $thumb, $list . '_' . $thumb . '_');
                if ($includeAllFiles) {
                    $select[$thumb][] = "GROUP_CONCAT(`{$thumb}`.`url` SEPARATOR ',') as `{$list}_{$thumb}`";
                }
            }
        }
    }
    unset($scriptProperties[$v]);
}

// where Files
if (!empty($scriptProperties['whereFiles'])) {
    $Files = json_decode($scriptProperties['whereFiles'], true);
    foreach ($Files as $key => $value) {
        $var = preg_replace('#\:.*#', '', $key);
        $key = str_replace($var, $var . '.value', $key);
        if (in_array($var, $leftJoinFiles) OR in_array($var, $innerJoinFiles)) {
            $where[$key] = $value;
        }
    }
}

$default = array(
    'class'     => $class,
    'where'     => $where,
    'leftJoin'  => $leftJoin,
    'innerJoin' => $innerJoin,
    'select'    => $select,
    'sortby'    => "{$class}.id",
    'sortdir'   => 'ASC',
    'groupby'   => implode(', ', $groupby)
);

$output = '';
/** @var modSnippet $snippet */
if ($snippet = $modx->getObject('modSnippet', array('name' => $element))) {
    $scriptProperties = array_merge($default, $scriptProperties);
    $snippet->setCacheable(false);
    $output = $snippet->process($scriptProperties);
}

return $output;