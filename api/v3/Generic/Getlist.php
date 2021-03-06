<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * @package CiviCRM_APIv3
 */

/**
 * Generic api wrapper used for quicksearch and autocomplete.
 *
 * @param array $apiRequest
 *
 * @return mixed
 */
function civicrm_api3_generic_getList($apiRequest) {
  $entity = _civicrm_api_get_entity_name_from_camel($apiRequest['entity']);
  $request = $apiRequest['params'];
  $meta = civicrm_api3_generic_getfields(['action' => 'get'] + $apiRequest, FALSE);

  // Hey api, would you like to provide default values?
  $fnName = "_civicrm_api3_{$entity}_getlist_defaults";
  $defaults = function_exists($fnName) ? $fnName($request) : [];
  _civicrm_api3_generic_getList_defaults($entity, $request, $defaults, $meta['values']);

  // Hey api, would you like to format the search params?
  $fnName = "_civicrm_api3_{$entity}_getlist_params";
  $fnName = function_exists($fnName) ? $fnName : '_civicrm_api3_generic_getlist_params';
  $fnName($request);

  $request['params']['check_permissions'] = !empty($apiRequest['params']['check_permissions']);
  $result = civicrm_api3($entity, 'get', $request['params']);

  // Hey api, would you like to format the output?
  $fnName = "_civicrm_api3_{$entity}_getlist_output";
  $fnName = function_exists($fnName) ? $fnName : '_civicrm_api3_generic_getlist_output';
  $values = $fnName($result, $request, $entity, $meta['values']);

  _civicrm_api3_generic_getlist_postprocess($result, $request, $values);

  $output = ['page_num' => $request['page_num']];

  // Limit is set for searching but not fetching by id
  if (!empty($request['params']['options']['limit'])) {
    // If we have an extra result then this is not the last page
    $last = $request['params']['options']['limit'] - 1;
    $output['more_results'] = isset($values[$last]);
    unset($values[$last]);
  }

  return civicrm_api3_create_success($values, $request['params'], $entity, 'getlist', CRM_Core_DAO::$_nullObject, $output);
}

/**
 * Set defaults for api.getlist.
 *
 * @param string $entity
 * @param array $request
 * @param array $apiDefaults
 * @param array $fields
 */
function _civicrm_api3_generic_getList_defaults($entity, &$request, $apiDefaults, $fields) {
  $defaults = [
    'page_num' => 1,
    'input' => '',
    'image_field' => NULL,
    'color_field' => isset($fields['color']) ? 'color' : NULL,
    'id_field' => $entity == 'option_value' ? 'value' : 'id',
    'description_field' => [],
    'add_wildcard' => Civi::settings()->get('includeWildCardInName'),
    'params' => [],
    'extra' => [],
  ];
  // Find main field from meta
  foreach (['sort_name', 'title', 'label', 'name', 'subject'] as $field) {
    if (isset($fields[$field])) {
      $defaults['label_field'] = $defaults['search_field'] = $field;
      break;
    }
  }
  // Find fields to be used for the description
  foreach (['description'] as $field) {
    if (isset($fields[$field])) {
      $defaults['description_field'][] = $field;
    }
  }
  $resultsPerPage = Civi::settings()->get('search_autocomplete_count');
  if (isset($request['params']) && isset($apiDefaults['params'])) {
    $request['params'] += $apiDefaults['params'];
  }
  $request += $apiDefaults + $defaults;
  // Default api params
  $params = [
    'sequential' => 1,
    'options' => [],
  ];
  // When searching e.g. autocomplete
  if ($request['input']) {
    $params[$request['search_field']] = ['LIKE' => ($request['add_wildcard'] ? '%' : '') . $request['input'] . '%'];
  }
  // When looking up a field e.g. displaying existing record
  if (!empty($request['id'])) {
    if (is_string($request['id']) && strpos($request['id'], ',')) {
      $request['id'] = explode(',', trim($request['id'], ', '));
    }
    // Don't run into search limits when prefilling selection
    $params['options']['limit'] = NULL;
    unset($params['options']['offset'], $request['params']['options']['limit'], $request['params']['options']['offset']);
    $params[$request['id_field']] = is_array($request['id']) ? ['IN' => $request['id']] : $request['id'];
  }
  $request['params'] += $params;

  $request['params']['options'] += [
    // Add pagination parameters
    'sort' => $request['label_field'],
    // Adding one extra result allows us to see if there are any more
    'limit' => $resultsPerPage + 1,
    // Because sql is zero-based
    'offset' => ($request['page_num'] - 1) * $resultsPerPage,
  ];
}

/**
 * Fallback implementation of getlist_params. May be overridden by individual apis.
 *
 * @param array $request
 */
function _civicrm_api3_generic_getlist_params(&$request) {
  $fieldsToReturn = [$request['id_field'], $request['label_field']];
  if (!empty($request['image_field'])) {
    $fieldsToReturn[] = $request['image_field'];
  }
  if (!empty($request['color_field'])) {
    $fieldsToReturn[] = $request['color_field'];
  }
  if (!empty($request['description_field'])) {
    $fieldsToReturn = array_merge($fieldsToReturn, (array) $request['description_field']);
  }
  $request['params']['return'] = array_unique(array_merge($fieldsToReturn, $request['extra']));
}

/**
 * Fallback implementation of getlist_output. May be overridden by individual api functions.
 *
 * @param array $result
 * @param array $request
 * @param string $entity
 * @param array $fields
 *
 * @return array
 */
function _civicrm_api3_generic_getlist_output($result, $request, $entity, $fields) {
  $output = [];
  if (!empty($result['values'])) {
    foreach ($result['values'] as $row) {
      $data = [
        'id' => $row[$request['id_field']],
        'label' => $row[$request['label_field']],
      ];
      if (!empty($request['description_field'])) {
        $data['description'] = [];
        foreach ((array) $request['description_field'] as $field) {
          if (!empty($row[$field])) {
            if (!isset($fields[$field]['pseudoconstant'])) {
              $data['description'][] = $row[$field];
            }
            else {
              $data['description'][] = CRM_Core_PseudoConstant::getLabel(
                _civicrm_api3_get_BAO($entity),
                $field,
                $row[$field]
              );
            }
          }
        }
      };
      if (!empty($request['image_field'])) {
        $data['image'] = $row[$request['image_field']] ?? '';
      }
      if (isset($row[$request['color_field']])) {
        $data['color'] = $row[$request['color_field']];
      }
      $output[] = $data;
    }
  }
  return $output;
}

/**
 * Common postprocess for getlist output
 *
 * @param $result
 * @param $request
 * @param $values
 */
function _civicrm_api3_generic_getlist_postprocess($result, $request, &$values) {
  $chains = [];
  foreach ($request['params'] as $field => $param) {
    if (substr($field, 0, 4) === 'api.') {
      $chains[] = $field;
    }
  }
  if (!empty($result['values'])) {
    foreach (array_values($result['values']) as $num => $row) {
      foreach ($request['extra'] as $field) {
        $values[$num]['extra'][$field] = $row[$field] ?? NULL;
      }
      foreach ($chains as $chain) {
        $values[$num][$chain] = $row[$chain] ?? NULL;
      }
    }
  }
}

/**
 * Provide metadata for this api
 *
 * @param array $params
 * @param array $apiRequest
 */
function _civicrm_api3_generic_getlist_spec(&$params, $apiRequest) {
  $params += [
    'page_num' => [
      'title' => 'Page Number',
      'description' => "Current page of a multi-page lookup",
      'type' => CRM_Utils_Type::T_INT,
    ],
    'input' => [
      'title' => 'Search Input',
      'description' => "String to search on",
      'type' => CRM_Utils_Type::T_TEXT,
    ],
    'params' => [
      'title' => 'API Params',
      'description' => "Additional filters to send to the {$apiRequest['entity']} API.",
    ],
    'extra' => [
      'title' => 'Extra',
      'description' => 'Array of additional fields to return.',
    ],
    'image_field' => [
      'title' => 'Image Field',
      'description' => "Field that this entity uses to store icons (usually automatic)",
      'type' => CRM_Utils_Type::T_TEXT,
    ],
    'id_field' => [
      'title' => 'ID Field',
      'description' => "Field that uniquely identifies this entity (usually automatic)",
      'type' => CRM_Utils_Type::T_TEXT,
    ],
    'description_field' => [
      'title' => 'Description Field',
      'description' => "Field that this entity uses to store summary text (usually automatic)",
      'type' => CRM_Utils_Type::T_TEXT,
    ],
    'label_field' => [
      'title' => 'Label Field',
      'description' => "Field to display as title of results (usually automatic)",
      'type' => CRM_Utils_Type::T_TEXT,
    ],
    'search_field' => [
      'title' => 'Search Field',
      'description' => "Field to search on (assumed to be the same as label field unless otherwise specified)",
      'type' => CRM_Utils_Type::T_TEXT,
    ],
  ];
}
