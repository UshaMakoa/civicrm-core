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
 *
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 * $Id$
 *
 */
class CRM_Event_BAO_ParticipantPayment extends CRM_Event_DAO_ParticipantPayment {

  /**
   * Creates or updates a participant payment record.
   *
   * @param array $params
   *   of values to initialize the record with.
   * @param array $ids
   *   deprecated array.
   *
   * @return object
   *   the partcipant payment record
   */
  public static function create(&$params, $ids = []) {
    $id = $params['id'] ?? $ids['id'] ?? NULL;
    if ($id) {
      CRM_Utils_Hook::pre('edit', 'ParticipantPayment', $id, $params);
    }
    else {
      CRM_Utils_Hook::pre('create', 'ParticipantPayment', NULL, $params);
    }

    $participantPayment = new CRM_Event_BAO_ParticipantPayment();
    $participantPayment->copyValues($params);
    if ($id) {
      $participantPayment->id = $id;
    }
    else {
      $participantPayment->find(TRUE);
    }
    $participantPayment->save();

    if (empty($participantPayment->contribution_id)) {
      // For an id update contribution_id may be unknown. We want it
      // further down so perhaps get it before the hooks.
      $participantPayment->find(TRUE);
    }
    if ($id) {
      CRM_Utils_Hook::post('edit', 'ParticipantPayment', $participantPayment->id, $participantPayment);
    }
    else {
      CRM_Utils_Hook::post('create', 'ParticipantPayment', $participantPayment->id, $participantPayment);
    }

    //generally if people are creating participant_payments via the api they won't be setting the line item correctly - we can't help them if they are doing complex transactions
    // but if they have a single line item for the contribution we can assume it should refer to the participant line
    $lineItemCount = CRM_Core_DAO::singleValueQuery("select count(*) FROM civicrm_line_item WHERE contribution_id = %1", [
      1 => [
        $participantPayment->contribution_id,
        'Integer',
      ],
    ]);
    if ($lineItemCount == 1) {
      $sql = "UPDATE civicrm_line_item li
      SET entity_table = 'civicrm_participant', entity_id = %1
      WHERE contribution_id = %2 AND entity_table = 'civicrm_contribution'";
      CRM_Core_DAO::executeQuery($sql, [
        1 => [$participantPayment->participant_id, 'Integer'],
        2 => [$participantPayment->contribution_id, 'Integer'],
      ]);
    }

    return $participantPayment;
  }

  /**
   * Delete the record that are associated with this ParticipantPayment.
   * Also deletes the associated contribution for this participant
   *
   * @param array $params
   *   Associative array whose values match the record to be deleted.
   *
   * @return bool
   *   true if deleted false otherwise
   */
  public static function deleteParticipantPayment($params) {
    $participantPayment = new CRM_Event_DAO_ParticipantPayment();

    $valid = FALSE;
    foreach ($params as $field => $value) {
      if (!empty($value)) {
        $participantPayment->$field = $value;
        $valid = TRUE;
      }
    }

    if (!$valid) {
      CRM_Core_Error::fatal();
    }

    if ($participantPayment->find(TRUE)) {
      CRM_Utils_Hook::pre('delete', 'ParticipantPayment', $participantPayment->id, $params);
      CRM_Contribute_BAO_Contribution::deleteContribution($participantPayment->contribution_id);
      $participantPayment->delete();
      CRM_Utils_Hook::post('delete', 'ParticipantPayment', $participantPayment->id, $participantPayment);
      return $participantPayment;
    }
    return FALSE;
  }

}
