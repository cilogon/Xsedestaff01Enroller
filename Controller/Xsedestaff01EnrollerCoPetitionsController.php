<?php

App::uses('CoPetitionsController', 'Controller');
App::uses('XsedestaffPetition', 'Xsedestaff01Enroller.Model');
 
class Xsedestaff01EnrollerCoPetitionsController extends CoPetitionsController {
  // Class name, used by Cake
  public $name = "Xsedestaff01EnrollerCoPetitions";
  public $uses = array("CoPetition");

  /**
   * Plugin functionality following petitionerAttributes step
   *
   * @param Integer $id CO Petition ID
   * @param Array $onFinish URL, in Cake format
   */
   
  protected function execute_plugin_petitionerAttributes($id, $onFinish) {
    $args = array();
    $args['conditions']['CoPetition.id'] = $id;
    $args['contain']['EnrolleeCoPerson']['CoOrgIdentityLink'] = 'OrgIdentity';
    $args['contain']['EnrolleeCoPerson'][] = 'Name';
    $args['contain']['EnrolleeCoPerson'][] = 'Identifier';

    $petition = $this->CoPetition->find('first', $args);
    $this->log("Petition is " . print_r($petition, true));

    $coId = $petition['CoPetition']['co_id'];
    $coPersonId = $petition['CoPetition']['enrollee_co_person_id'];
    $coPersonRoleId = $petition['CoPetition']['enrollee_co_person_role_id'];
    $givenName = $petition['EnrolleeCoPerson']['Name'][0]['given'];
    $familyName = $petition['EnrolleeCoPerson']['Name'][0]['family'];
    $displayName = "$givenName $familyName";

    $xsedeUsername = null;
    foreach($petition['EnrolleeCoPerson']['Identifier'] as $identifier) {
      if($identifier['type'] == 'xsedeusername') {
        $xsedeUsername = $identifier['identifier'];
        break;
      }
    }

    // We assume there is only one OrgIdentity at this point.
    $orgId = $petition['EnrolleeCoPerson']['CoOrgIdentityLink'][0]['org_identity_id'];

    // Set the CoPetition ID to use as a hidden form element.
    $this->set('co_petition_id', $id);

    // Set display name to use in view.
    $this->set('displayName', $displayName);

    // Save the onFinish URL to which we must redirect after receiving
    // the incoming POST data.
    if(!$this->Session->check('xsede.plugin.staff_01_enroller.onFinish')) {
      $this->Session->write('xsede.plugin.staff_01_enroller.onFinish', $onFinish);
    }

    // Process incoming POST data.
    if($this->request->is('post')) {
      $this->log("Incoming POST data is " . print_r($this->data, true));

      // Validate incoming data.
      $data = $this->validatePost();

      if(!$data) {
        // The call to validatePost() sets $this->Flash if there are any validation
        // errors so just return.
        return;
      }

      // Save the XSEDE staff petition data.
      $petitionModel = new XsedestaffPetition();
      $petitionModel->clear();
      $petitionData = array();
      $petitionData['XsedestaffPetition'] = $this->data['XsedestaffPetition'];
      if(!$petitionModel->save($petitionData)) {
        $this->log("Error saving XsedestaffPetition data " . print_r($petitionData, true));
        $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
        $this->redirect("/");
      }

      // Save the XSEDE staff compute allocation data.
      foreach($this->data['XsedestaffComputeAllocation'] as $label => $allocation) {
        if($allocation['allocation']) {
          $petitionModel->XsedestaffComputeAllocation->clear();
          $data = array();
          $data['XsedestaffComputeAllocation']['xsedestaff_petition_id'] = $petitionModel->id;
          $data['XsedestaffComputeAllocation']['allocation'] = $label;
          if(!$petitionModel->XsedestaffComputeAllocation->save($data)) {
            $this->log("Error saving XsedestaffComputeAllocation data " . print_r($data, true));
            $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
            $this->redirect("/");
          };
        }
      }

      // Add to the L3 or higher group if applicable.
      if($this->data['XsedestaffPetition']['l3_or_higher']) {
        $l3ManagerGroupName = Configure::read('Xsede.L3Manager.group.name');

        $args = array();
        $args['conditions']['CoGroup.name'] = $l3ManagerGroupName;
        $args['contain'] = false;

        $l3ManagerGroup = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->find('first', $args);
        if(empty($l3ManagerGroup)) {
          $this->log("Error unable to find L3 Manager group with name " . print_r($l3ManagerGroupName, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }

        $this->CoPetition->EnrolleeCoPerson->CoGroupMember->clear();

        $data = array();
        $data['CoGroupMember']['co_group_id'] = $l3ManagerGroup['CoGroup']['id'];
        $data['CoGroupMember']['co_person_id'] = $coPersonId;
        $data['CoGroupMember']['member'] = true;
        $data['CoGroupMember']['owner'] = false;

        if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->save($data)) {
          $this->log("Error saving CoGroupMember" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }
      } 

      // Add to the Funded By XSEDE group if applicable.
      if($this->data['XsedestaffPetition']['funded_by_xsede']) {
        $fundedByXsedeGroupName = Configure::read('Xsede.FundedByXsede.group.name');

        $args = array();
        $args['conditions']['CoGroup.name'] = $fundedByXsedeGroupName;
        $args['contain'] = false;

        $fundedByXsedeGroup = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->find('first', $args);
        if(empty($fundedByXsedeGroup)) {
          $this->log("Error unable to find Funded by XSEDE group with name " . print_r($fundedByXsedeGroupName, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }

        $this->CoPetition->EnrolleeCoPerson->CoGroupMember->clear();

        $data = array();
        $data['CoGroupMember']['co_group_id'] = $fundedByXsedeGroup['CoGroup']['id'];
        $data['CoGroupMember']['co_person_id'] = $coPersonId;
        $data['CoGroupMember']['member'] = true;
        $data['CoGroupMember']['owner'] = false;

        if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->save($data)) {
          $this->log("Error saving CoGroupMember" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }
      } 

      // Add to the home institution group.
      $groupName = _txt('pl.xsedestaff01_enroller.home_institution_enum')[$this->data['XsedestaffPetition']['home_institution']];
      $args = array();
      $args['conditions']['CoGroup.name'] = $groupName;
      $args['contain'] = false;

      $group = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->find('first', $args);
      if(empty($group)) {
        $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->clear();
        $data = array();
        $data['CoGroup']['co_id'] = $coId;
        $data['CoGroup']['name'] = $groupName;
        $data['CoGroup']['description'] = 'Staff with home institution ' . $groupName;
        $data['CoGroup']['open'] = false;
        $data['CoGroup']['status'] = SuspendableStatusEnum::Active;
        $data['CoGroup']['group_type'] = GroupEnum::Standard;

        if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->save($data)) {
          $this->log("Error saving CoGroup" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }

        $groupId = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->id;
      } else {
        $groupId = $group['CoGroup']['id'];
      }

      $this->CoPetition->EnrolleeCoPerson->CoGroupMember->clear();

      $data = array();
      $data['CoGroupMember']['co_group_id'] = $groupId;
      $data['CoGroupMember']['co_person_id'] = $coPersonId;
      $data['CoGroupMember']['member'] = true;
      $data['CoGroupMember']['owner'] = false;

      if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->save($data)) {
        $this->log("Error saving CoGroupMember" . print_r($data, true));
        $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
        $this->redirect("/");
      }

      // Create an ad-hoc attribute on role to display home institution supervisor.
      $this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->clear();
      $data = array();
      $data['AdHocAttribute']['tag'] = Configure::read('Xsede.HomeInstitutionSupervisor.adhoc.name');
      $data['AdHocAttribute']['value'] = $this->data['XsedestaffPetition']['home_institution_supervisor'];
      $data['AdHocAttribute']['co_person_role_id'] = $coPersonRoleId;

      if(!$this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->save($data)) {
          $this->log("Error saving AdHocAttribute" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
      }

      // Create an ad-hoc attribute on role to display home institution supervisor email.
      $this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->clear();
      $data = array();
      $data['AdHocAttribute']['tag'] = Configure::read('Xsede.HomeInstitutionSupervisorEmail.adhoc.name');
      $data['AdHocAttribute']['value'] = $this->data['XsedestaffPetition']['home_institution_supervisor_email'];
      $data['AdHocAttribute']['co_person_role_id'] = $coPersonRoleId;

      if(!$this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->save($data)) {
          $this->log("Error saving AdHocAttribute" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
      }

      // Add to the Staff Portal group if applicable.
      if($this->data['XsedestaffPetition']['staff_portal']) {
        $staffPortalGroupName = Configure::read('Xsede.StaffPortal.group.name');

        $args = array();
        $args['conditions']['CoGroup.name'] = $staffPortalGroupName;
        $args['contain'] = false;

        $staffPortalGroup = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->find('first', $args);
        if(empty($staffPortalGroup)) {
          $this->log("Error unable to find Staff Portal group with name " . print_r($staffPortalGroupName, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }

        $this->CoPetition->EnrolleeCoPerson->CoGroupMember->clear();

        $data = array();
        $data['CoGroupMember']['co_group_id'] = $staffPortalGroup['CoGroup']['id'];
        $data['CoGroupMember']['co_person_id'] = $coPersonId;
        $data['CoGroupMember']['member'] = true;
        $data['CoGroupMember']['owner'] = false;

        if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->save($data)) {
          $this->log("Error saving CoGroupMember" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }
      } 

      // Add to the RT ticket system group if applicable.
      if($this->data['XsedestaffPetition']['rt_ticket_system']) {
        $rtGroupName = Configure::read('Xsede.RT.group.name');

        $args = array();
        $args['conditions']['CoGroup.name'] = $rtGroupName;
        $args['contain'] = false;

        $rtGroup = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->find('first', $args);
        if(empty($rtGroup)) {
          $this->log("Error unable to find RT group with name " . print_r($rtGroupName, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }

        $this->CoPetition->EnrolleeCoPerson->CoGroupMember->clear();

        $data = array();
        $data['CoGroupMember']['co_group_id'] = $rtGroup['CoGroup']['id'];
        $data['CoGroupMember']['co_person_id'] = $coPersonId;
        $data['CoGroupMember']['member'] = true;
        $data['CoGroupMember']['owner'] = false;

        if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->save($data)) {
          $this->log("Error saving CoGroupMember" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }
      } 

      // Create an ad-hoc attribute on role to display other resources.
      if(!empty($this->data['XsedestaffPetition']['other_resources'])) {
        $this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->clear();
        $data = array();
        $data['AdHocAttribute']['tag'] = Configure::read('Xsede.OtherResources.adhoc.name');
        $data['AdHocAttribute']['value'] = $this->data['XsedestaffPetition']['other_resources'];
        $data['AdHocAttribute']['co_person_role_id'] = $coPersonRoleId;

        if(!$this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->save($data)) {
            $this->log("Error saving AdHocAttribute" . print_r($data, true));
            $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
            $this->redirect("/");
        }
      }

      // Create an ad-hoc attribute on role to display additional information.
      if(!empty($this->data['XsedestaffPetition']['additional_information'])) {
        $this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->clear();
        $data = array();
        $data['AdHocAttribute']['tag'] = Configure::read('Xsede.AdditionalInformation.adhoc.name');
        $data['AdHocAttribute']['value'] = $this->data['XsedestaffPetition']['additional_information'];
        $data['AdHocAttribute']['co_person_role_id'] = $coPersonRoleId;

        if(!$this->CoPetition->EnrolleeCoPersonRole->AdHocAttribute->save($data)) {
            $this->log("Error saving AdHocAttribute" . print_r($data, true));
            $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
            $this->redirect("/");
        }
      }

      // Process compute allocation requests.
      foreach($this->data['XsedestaffComputeAllocation'] as $resource => $allocation) {
        $groupName = _txt('pl.xsedestaff01_enroller.compute_allocation_enum')[$resource];
        $args = array();
        $args['conditions']['CoGroup.name'] = $groupName;
        $args['contain'] = false;

        $group = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->find('first', $args);
        if(empty($group)) {
          $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->clear();
          $data = array();
          $data['CoGroup']['co_id'] = $coId;
          $data['CoGroup']['name'] = $groupName;
          $data['CoGroup']['description'] = 'Staff with compute allocation' . $groupName;
          $data['CoGroup']['open'] = false;
          $data['CoGroup']['status'] = SuspendableStatusEnum::Active;
          $data['CoGroup']['group_type'] = GroupEnum::Standard;

          if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->save($data)) {
            $this->log("Error saving CoGroup" . print_r($data, true));
            $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
            $this->redirect("/");
          }

          $groupId = $this->CoPetition->EnrolleeCoPerson->CoGroupMember->CoGroup->id;
        } else {
          $groupId = $group['CoGroup']['id'];
        }

        if($allocation['allocation']) {
          $this->CoPetition->EnrolleeCoPerson->CoGroupMember->clear();

          $data = array();
          $data['CoGroupMember']['co_group_id'] = $groupId;
          $data['CoGroupMember']['co_person_id'] = $coPersonId;
          $data['CoGroupMember']['member'] = true;
          $data['CoGroupMember']['owner'] = false;

          if(!$this->CoPetition->EnrolleeCoPerson->CoGroupMember->save($data)) {
            $this->log("Error saving CoGroupMember" . print_r($data, true));
            $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
            $this->redirect("/");
          }
        }
      }

      // Add the XSEDE IdP ePPN as a login Identifier to the Organizational Identity.
      if(!empty($xsedeUsername)) {
        $this->CoPetition->EnrolleeCoPerson->CoOrgIdentityLink->OrgIdentity->Identifier->clear();

        $data = array();
        $data['Identifier']['identifier'] = $xsedeUsername . '@xsede.org';
        $data['Identifier']['type'] = IdentifierEnum::ePPN;
        $data['Identifier']['login'] = true;
        $data['Identifier']['status'] = SuspendableStatusEnum::Active;
        $data['Identifier']['org_identity_id'] = $orgId;

        if(!$this->CoPetition->EnrolleeCoPerson->CoOrgIdentityLink->OrgIdentity->Identifier->save($data)) {
          $this->log("Error saving Identifier" . print_r($data, true));
          $this->Flash->set(_txt('pl.xsedestaff01_enroller.error.xsedestaffpetition.save'), array('key' => 'error'));
          $this->redirect("/");
        }
      }

      $onFinish = $this->Session->consume('xsede.plugin.staff_01_enroller.onFinish');

      // Done processing all POST data so redirect to continue enrollment flow.
      $this->redirect($onFinish);
    }

    // GET, so fall through to display the form.

  }

  /**
   * Validate POST data from an add action.
   *
   * @return Array of validated data ready for saving or false if not validated.
   */

  private function validatePost() {
    $data = $this->request->data;

    // Trim leading and trailing whitespace from user input.
    array_walk_recursive($data, function (&$value,$key){ 
      if(is_string($value)) { 
        $value = trim($value); 
      } 
    });

    // We validate necessary fields here in the controller so that
    // we can leverage saveAssociated to save the data with validate
    // set to false. When it is set to tru and there are multiple rows
    // of associated data validation fails.

    // Validate the XsedestaffPetition fields.
    $petitionModel = new XsedestaffPetition();
    $petitionModel->clear();
    $petitionData = array();
    $petitionData['XsedestaffPetition'] = $data['XsedestaffPetition'];
    $petitionModel->set($data);

    $fields = array();
    $fields[] = 'home_institution_supervisor_email';

    $args = array();
    $args['fieldList'] = $fields;

    if(!$petitionModel->validates($args)) {
      $this->Flash->set(_txt('er.fields'), array('key' => 'error'));
      return false;
    }

    return $data;
  }
}
