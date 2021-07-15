<?

class XsedestaffPetition extends AppModel {
  // Define class name for cake
  public $name = "XsedestaffPetition";

  // Add behaviors
  public $actsAs = array(
    'Containable'
  );

  // Association rules from this model to other models
  public $belongsTo = array(
    "CoPetition"
  );

  public $hasMany = array(
    "XsedestaffComputeAllocation"
  );

  // Validation rules for table elements
  public $validate = array(
    'co_petition_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'l3_or_higher' => array(
      'rule' => array('boolean'),
      'required' => true,
      'allowEmtpy' => true
    ),
    'funded_by_xsede' => array(
      'rule' => array('boolean'),
      'required' => true,
      'allowEmtpy' => true
    ),
    'home_institution' => array(
      'rule' => array('inList', array(XsedeStaffHomeInstitutionEnum::Cornell,
                                      XsedeStaffHomeInstitutionEnum::GeorgiaTech,
                                      XsedeStaffHomeInstitutionEnum::Indiana,
                                      XsedeStaffHomeInstitutionEnum::Internet2,
                                      XsedeStaffHomeInstitutionEnum::NCARUCAR,
                                      XsedeStaffHomeInstitutionEnum::NCSA,
                                      XsedeStaffHomeInstitutionEnum::NICS,
                                      XsedeStaffHomeInstitutionEnum::OSC,
                                      XsedeStaffHomeInstitutionEnum::Oklahoma,
                                      XsedeStaffHomeInstitutionEnum::PSC,
                                      XsedeStaffHomeInstitutionEnum::Purdue,
                                      XsedeStaffHomeInstitutionEnum::SDSC,
                                      XsedeStaffHomeInstitutionEnum::Shodor,
                                      XsedeStaffHomeInstitutionEnum::SURA,
                                      XsedeStaffHomeInstitutionEnum::TACC,
                                      XsedeStaffHomeInstitutionEnum::Chicago,
                                      XsedeStaffHomeInstitutionEnum::ISI,
                                      XsedeStaffHomeInstitutionEnum::Other)),
      'required' => true,
      'allowEmpty' => false
    ),
    'home_institution_supervisor' => array(
      'rule' => array('validateInput'),
      'required' => true,
      'allowEmpty' => false
    ),
    'home_institution_supervisor_email' => array(
      'rule' => array('email'),
      'required' => true,
      'allowEmpty' => false,
      'message' => 'Please enter a valid email address'
    ),
    'staff_portal' => array(
      'rule' => array('boolean'),
      'required' => true,
      'allowEmpty' => true
    ),
    'email_distribution_lists' => array(
      'rule' => array('boolean'),
      'required' => true,
      'allowEmpty' => true
    ),
    'rt_ticket_system' => array(
      'rule' => array('boolean'),
      'required' => true,
      'allowEmpty' => true
    ),
    'other_resources' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'additional_information' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
  );



}
