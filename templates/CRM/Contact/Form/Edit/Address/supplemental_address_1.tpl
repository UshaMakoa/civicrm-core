{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
{if !empty($form.address.$blockId.supplemental_address_1)}
  <tr>
     <td colspan="2">
         {$form.address.$blockId.supplemental_address_1.label} {help id="id-supplemental-address" file="CRM/Contact/Form/Contact.hlp"}<br />
         {$form.address.$blockId.supplemental_address_1.html}
     </td>
  </tr>
{/if}
