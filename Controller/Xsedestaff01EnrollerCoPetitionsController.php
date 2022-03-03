<?php

App::uses('CoPetitionsController', 'Controller');
App::uses('XsedestaffPetition', 'Xsedestaff01Enroller.Model');
 
class Xsedestaff01EnrollerCoPetitionsController extends CoPetitionsController {
  // Class name, used by Cake
  public $name = "Xsedestaff01EnrollerCoPetitions";
  public $uses = array("CoPetition");
  
  /**
   * Plugin functionality following finalize step
   *
   * @param Integer $id CO Petition ID
   * @param Array $onFinish URL, in Cake format
   */

  protected function execute_plugin_finalize($id, $onFinish) {
    $args = array();
    $args['conditions']['CoPetition.id'] = $id;
    $args['contain']['CoEnrollmentFlow'] = 'CoEnrollmentFlowFinMessageTemplate';
    $args['contain']['EnrolleeCoPerson'] = array('PrimaryName', 'Identifier');
    $args['contain']['EnrolleeCoPerson']['CoGroupMember'] = 'CoGroup';
    $args['contain']['EnrolleeCoPerson']['CoPersonRole'][] = 'Cou';
    $args['contain']['EnrolleeCoPerson']['CoPersonRole']['SponsorCoPerson'][] = 'PrimaryName';
    $args['contain']['EnrolleeOrgIdentity'] = array('EmailAddress', 'PrimaryName');

    $petition = $this->CoPetition->find('first', $args);
    $this->log("Finalize: Petition is " . print_r($petition, true));

    // Find the XsedestaffPetition.
    $petitionModel = new XsedestaffPetition();

    $args = array();
    $args['conditions']['XsedestaffPetition.co_petition_id'] = $id;
    $args['contain'] = 'XsedestaffComputeAllocation';

    $xsedeStaffPetition = $petitionModel->find('first', $args);

    $this->log("Finalize: XsedestaffPetition is " . print_r($xsedeStaffPetition, true));

    // Notify the enrollee.
    $this->notifyEnrolleeFinalize($petition);

    // Notify the project manager.
    $this->notifyProjectManagerFinalize($petition);

    // Notify the RT coordinator if RT was requested.
    if($xsedeStaffPetition['XsedestaffPetition']['rt_ticket_system']) {
      $this->notifyRtCoordinatorFinalize($petition, $xsedeStaffPetition);
    }

    // Notify the members of the Onboarding Managers group.
    $this->notifyOnboardingManagersFinalize($petition);

    // This step is completed so redirect to continue the flow.
    $this->redirect($onFinish);
  }

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
    $this->log("Petitioner Attributes: Petition is " . print_r($petition, true));

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
   *
   *
   */
  private function notifyEnrolleeFinalize($petition) {

    if(empty($petition['CoPetition']['cou_id'])) {
      $this->log("Finalize: could not find COU ID from petition");
      return;
    }

    $coPetitionId= $petition['CoPetition']['id'];
    $coId = $petition['CoPetition']['co_id'];
    $couId = $petition['CoPetition']['cou_id'];

    // Find the Project Manager for the COU.
    $args = array();
    $args['conditions']['CoPersonRole.affiliation'] = 'projectmanager';
    $args['conditions']['CoPersonRole.status'] = StatusEnum::Active;
    $args['contain'][] = 'Cou';
    $args['contain']['CoPerson'] = array('PrimaryName', 'EmailAddress');

    $projectManagerRoles =
      $this
        ->CoPetition
        ->Co
        ->CoPerson
        ->CoPersonRole
        ->find('all', $args);

    foreach($projectManagerRoles as $pm) {
      $childCous = $this->CoPetition->Co->Cou->childCousById($pm['Cou']['id'], $coId);
      foreach($childCous as $childId => $childName) {
        if($childId == $couId) {
          $projectManagerCoPersonId = $pm['CoPersonRole']['co_person_id'];
          $projectManagerName = $pm['CoPerson']['PrimaryName'];
          $projectManagerEmail = $pm['CoPerson']['EmailAddress'][0]['mail']; // Take the first email for now.
          break 2;
        }
      }
    }

    $this->log("Finalize: Project manager name is " . print_r($projectManagerName, true));
    $this->log("Finalize: Project manager email is " . print_r($projectManagerEmail, true));

    // Find the message template.
    $args = array();
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.context'] = MessageTemplateEnum::Plugin;
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.description'] = "Onboard: Mail to Enrollee";
    $args['contain'] = false;

    $template =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->find('first', $args);

    // Determine the mail list groups to which the enrollee has been added.
    $mailListHtml = "";
    foreach($petition['EnrolleeCoPerson']['CoGroupMember'] as $m) {
      $groupName = $m['CoGroup']['name'];
      $matches = array();
      if(preg_match('/(^.+)-mail$/', $groupName, $matches)) {
        $listPrefix = $matches[1];
        $list = $listPrefix . '@xsede.org';
        $mailListHtml = $mailListHtml . "<li>$list</li>";
      }
    }

    $mailListHtml = "<ul>$mailListHtml</ul>";

    $substitutions = array();
    $substitutions['CO_PERSON'] = generateCn($petition['EnrolleeCoPerson']['PrimaryName']);
    $substitutions['PM_NAME'] = generateCn($projectManagerName);
    $substitutions['PM_EMAIL'] = $projectManagerEmail;
    $substitutions['MAIL_LISTS'] = $mailListHtml;

    $subject = null;
    $body = null;
    $cc = null;
    $bcc = null;
    $comment = 'This is a comment';

    $format = MessageFormatEnum::HTML;

    list($body, $subject, $format, $cc, $bcc) =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->getMessageTemplateFields($template['CoEnrollmentFlowFinMessageTemplate']);

    $subject = processTemplate($subject, $substitutions);
    $body = processTemplate($body, $substitutions);

    $this
      ->CoPetition
      ->Co
      ->CoPerson
      ->CoNotificationRecipient
      ->register(
          $petition['CoPetition']['enrollee_co_person_id'],
          null,
          null,
          'coperson',
          $petition['CoPetition']['enrollee_co_person_id'],
          ActionEnum::CoPetitionUpdated,
          $comment,
          array(
            'controller' => 'co_petitions',
            'action'     => 'view',
            'id'         => $coPetitionId
          ),
          false,
          null,
          $subject,
          $body,
          $cc,
          $bcc,
          $format);
  }

  /**
   *
   *
   */
  private function notifyOnboardingManagersFinalize($petition) {

    $coPetitionId= $petition['CoPetition']['id'];
    $coId = $petition['CoPetition']['co_id'];
    $couId = $petition['CoPetition']['cou_id'];

    // Find the Onboarding Managers group.
    $args = array();
    $args['conditions']['CoGroup.name'] = 'Onboarding Managers';
    $args['contain'] = false;

    $onboardingManagersCoGroup =
      $this
        ->CoPetition
        ->Co
        ->CoGroup
        ->find('first', $args);

    $onboardingManagersCoGroupId = $onboardingManagersCoGroup['CoGroup']['id'];

    // Find the message template.
    $args = array();
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.context'] = MessageTemplateEnum::Plugin;
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.description'] = "Onboard: Mail to Onboarding Managers";
    $args['contain'] = false;

    $template =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->find('first', $args);

    $substitutions = array();
    $substitutions['CO_PERSON'] = generateCn($petition['EnrolleeCoPerson']['PrimaryName']);

    $subject = null;
    $body = null;
    $cc = null;
    $bcc = null;
    $comment = 'This is a comment';

    $format = MessageFormatEnum::HTML;

    list($body, $subject, $format, $cc, $bcc) =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->getMessageTemplateFields($template['CoEnrollmentFlowFinMessageTemplate']);

    $subject = processTemplate($subject, $substitutions);
    $body = processTemplate($body, $substitutions);

    $this
      ->CoPetition
      ->Co
      ->CoPerson
      ->CoNotificationRecipient
      ->register(
          $petition['CoPetition']['enrollee_co_person_id'],
          null,
          null,
          'cogroup',
          $onboardingManagersCoGroupId,
          ActionEnum::CoPetitionUpdated,
          $comment,
          array(
            'controller' => 'co_petitions',
            'action'     => 'view',
            'id'         => $coPetitionId
          ),
          false,
          null,
          $subject,
          $body,
          $cc,
          $bcc,
          $format);
  }

  /**
   *
   *
   */
  private function notifyProjectManagerFinalize($petition) {

    if(empty($petition['CoPetition']['cou_id'])) {
      $this->log("Finalize: could not find COU ID from petition");
      return;
    }

    $coPetitionId= $petition['CoPetition']['id'];
    $coId = $petition['CoPetition']['co_id'];
    $couId = $petition['CoPetition']['cou_id'];

    // Find the Project Manager for the COU.
    $args = array();
    $args['conditions']['CoPersonRole.affiliation'] = 'projectmanager';
    $args['conditions']['CoPersonRole.status'] = StatusEnum::Active;
    $args['contain'] = 'Cou';

    $projectManagerRoles =
      $this
        ->CoPetition
        ->Co
        ->CoPerson
        ->CoPersonRole
        ->find('all', $args);

    foreach($projectManagerRoles as $pm) {
      $childCous = $this->CoPetition->Co->Cou->childCousById($pm['Cou']['id'], $coId);
      foreach($childCous as $childId => $childName) {
        if($childId == $couId) {
          $projectManagerCoPersonId = $pm['CoPersonRole']['co_person_id'];
          break 2;
        }
      }
    }

    $this->log("Finalize: Project manager Co Person ID is $projectManagerCoPersonId");

    // Find the message template.
    $args = array();
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.context'] = MessageTemplateEnum::Plugin;
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.description'] = "Onboard: Mail to Program Manager";
    $args['contain'] = false;

    $template =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->find('first', $args);

    $substitutions = array();
    $substitutions['CO_PERSON'] = generateCn($petition['EnrolleeCoPerson']['PrimaryName']);

    $subject = null;
    $body = null;
    $cc = null;
    $bcc = null;
    $comment = 'This is a comment';

    $format = MessageFormatEnum::Plaintext;

    list($body, $subject, $format, $cc, $bcc) =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->getMessageTemplateFields($template['CoEnrollmentFlowFinMessageTemplate']);

    $subject = processTemplate($subject, $substitutions);
    $body = processTemplate($body, $substitutions);

    $this
      ->CoPetition
      ->Co
      ->CoPerson
      ->CoNotificationRecipient
      ->register(
          $petition['CoPetition']['enrollee_co_person_id'],
          null,
          null,
          'coperson',
          $projectManagerCoPersonId,
          ActionEnum::CoPetitionUpdated,
          $comment,
          array(
            'controller' => 'co_petitions',
            'action'     => 'view',
            'id'         => $coPetitionId
          ),
          false,
          null,
          $subject,
          $body,
          $cc,
          $bcc,
          $format);
  }

  /**
   *
   *
   */
  private function notifyRtCoordinatorFinalize($petition) {

    if(empty($petition['CoPetition']['cou_id'])) {
      $this->log("Finalize: could not find COU ID from petition");
      return;
    }

    $coPetitionId= $petition['CoPetition']['id'];
    $coId = $petition['CoPetition']['co_id'];
    $couId = $petition['CoPetition']['cou_id'];

    // Find the RT Coordinator.
    $args = array();
    $args['conditions']['CoPersonRole.affiliation'] = 'rtcoordinator';
    $args['conditions']['CoPersonRole.status'] = StatusEnum::Active;
    $args['contain'] = false;

    $rtCoordinator =
      $this
        ->CoPetition
        ->Co
        ->CoPerson
        ->CoPersonRole
        ->find('first', $args); // We assume for now there is only one.

    $this->log("Finalize: RT Coordinator is " . print_r($rtCoordinator, true));

    $rtCoordinatorCoPersonId = $rtCoordinator['CoPersonRole']['co_person_id'];

    // Find the message template.
    $args = array();
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.context'] = MessageTemplateEnum::Plugin;
    $args['conditions']['CoEnrollmentFlowFinMessageTemplate.description'] = "Onboard: Mail to RT Coordinator";
    $args['contain'] = false;

    $template =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->find('first', $args);

    $substitutions = array();
    $substitutions['CO_PERSON'] = generateCn($petition['EnrolleeCoPerson']['PrimaryName']);

    $subject = null;
    $body = null;
    $cc = null;
    $bcc = null;
    $comment = 'This is a comment';

    $format = MessageFormatEnum::Plaintext;

    list($body, $subject, $format, $cc, $bcc) =
      $this
        ->CoPetition
        ->CoEnrollmentFlow
        ->CoEnrollmentFlowFinMessageTemplate
        ->getMessageTemplateFields($template['CoEnrollmentFlowFinMessageTemplate']);

    $subject = processTemplate($subject, $substitutions);
    $body = processTemplate($body, $substitutions);

    $this
      ->CoPetition
      ->Co
      ->CoPerson
      ->CoNotificationRecipient
      ->register(
          $petition['CoPetition']['enrollee_co_person_id'],
          null,
          null,
          'coperson',
          $rtCoordinatorCoPersonId,
          ActionEnum::CoPetitionUpdated,
          $comment,
          array(
            'controller' => 'co_petitions',
            'action'     => 'view',
            'id'         => $coPetitionId
          ),
          false,
          null,
          $subject,
          $body,
          $cc,
          $bcc,
          $format);
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
