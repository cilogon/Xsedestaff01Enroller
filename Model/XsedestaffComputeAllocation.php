<?

class XsedestaffComputeAllocation extends AppModel {
  // Define class name for cake
  public $name = "XsedestaffComputeAllocation";

  // Add behaviors
  public $actsAs = array(
    'Containable'
  );

  // Association rules from this model to other models
  public $belongsTo = array(
    "XsedestaffPetition"
  );

  // Validation rules for table elements
  public $validate = array(
    'xsedestaff_petition_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'allocation' => array(
      'rule' => array('inList', array(XsedeStaffComputeAllocationEnum::TG_ASC160050,
                                      XsedeStaffComputeAllocationEnum::TG_ASC160051,
                                      XsedeStaffComputeAllocationEnum::TG_ASC170016)),
      'required' => true,
      'allowEmpty' => false
    )
  );



}
