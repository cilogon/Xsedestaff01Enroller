<script type="text/javascript">

function js_local_onload() {
  $("#XsedestaffPetitionL3OrHigher").click(function(){
    $("#XsedestaffPetitionFundedByXsede").prop('checked', true);
  });


  $("#toggle_compute_allocations").click(function(e) {
    if ($("#xsedestaff_compute_allocation").is(":visible")) {
      $("#xsedestaff_compute_allocation").hide();
      $("#toggle_compute_allocations").attr("aria-expanded","false");
      $("#toggle_compute_allocations_arrow").text("arrow_drop_down");
    } else {
      $("#xsedestaff_compute_allocation").show();
      $("#toggle_compute_allocations").attr("aria-expanded","true");
      $("#toggle_compute_allocations_arrow").text("arrow_drop_up");
    }
  });



}

</script>


<?php

  $params = array();
  $params['title'] = _txt('pl.xsedestaff01_enroller.title', array($displayName));

  print $this->element("pageTitleAndButtons", $params);

  print $this->Form->create(
    'Xsedestaff01Enroller.XsedestaffPetition',
    array(
      'inputDefaults' => array(
        'label' => false,
        'div' => false
      )
    )
  );

 print $this->Form->hidden('co_petition_id', array('default' => $co_petition_id));

?> 

<ul id="xsedestaff_petition_1" class="fields form-list">
  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('l3_or_higher', _txt('pl.xsedestaff01_enroller.l3_or_higher', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('l3_or_higher'); ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('funded_by_xsede', _txt('pl.xsedestaff01_enroller.funded_by_xsede', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('funded_by_xsede'); ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('home_institution', _txt('pl.xsedestaff01_enroller.home_institution', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php 
        global $cm_lang, $cm_texts;
        $args = array();
        $args['options'] = $cm_texts[$cm_lang]['pl.xsedestaff01_enroller.home_institution_enum'];
        $args['empty'] = true;
        print $this->Form->input('home_institution', $args);
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('home_institution_supervisor', _txt('pl.xsedestaff01_enroller.home_institution_supervisor', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php
        $args = array();
        $args['maxlength'] = '32';
        print $this->Form->input('home_institution_supervisor', $args);
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('home_institution_supervisor_email', _txt('pl.xsedestaff01_enroller.home_institution_supervisor_email', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php
        $args = array();
        $args['maxlength'] = '32';
        print $this->Form->input('home_institution_supervisor_email', $args);
      ?>
    </div>
  </li>
</ul>

<h2>Resources to be granted</h2>

<ul id="xsedestaff_petition_2" class="fields form-list">
  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('staff_portal', _txt('pl.xsedestaff01_enroller.staff_portal', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('staff_portal'); ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('email_distribution_lists', _txt('pl.xsedestaff01_enroller.email_distribution_lists', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('email_distribution_lists'); ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('rt_ticket_system', _txt('pl.xsedestaff01_enroller.rt_ticket_system', array($displayName))); ?><span class="required">*</span>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('rt_ticket_system'); ?>
    </div>
  </li>

</ul>

<h2>
<button id="toggle_compute_allocations" class="cm-toggle" aria-expanded="false" aria-controls="xsedestaff_compute_allocation" type="button">
<span style="font-size: 1em; font-family: 'Noto Sans Bold','Noto Sans','Trebuchet MS',Arial,Helvetica,sans-serif;">Staff Compute Allocations</span>
<span id="toggle_compute_allocations_arrow" class="material-icons" style="font-size: 36px;">arrow_drop_down</span>
</button>
</h2>

<div id="xsedestaff_compute_allocation" style="display: none;">


<ul id="xsedestaff_petition_3" class="fields form-list">

  <?php
    global $cm_lang, $cm_texts;

    foreach($cm_texts[$cm_lang]['pl.xsedestaff01_enroller.compute_allocation_enum'] as $constant => $label) {
      print '<li>';
      print '<div class="field-name">';
      print '<div class="field-title">';
      print $this->Form->label('XsedestaffComputeAllocation.' . $constant . '.allocation', $label);
      print '</div>';
      print '</div>';
      print '<div class="field-info">';
      $args = array();
      $args['type'] = 'checkbox';
      print $this->Form->input('XsedestaffComputeAllocation.' . $constant . '.allocation', $args);
      print '</div>';
      print '</li>';
    }

  ?>


</ul>

</div>

<h2>Other</h2>

<ul id="xsedestaff_petition_4" class="fields form-list">

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('other_resources', _txt('pl.xsedestaff01_enroller.other_resources', array($displayName))); ?>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('other_resources'); ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print $this->Form->label('additional_information', _txt('pl.xsedestaff01_enroller.additional_information', array($displayName))); ?>
      </div>
    </div>
    <div class="field-info">
      <?php print $this->Form->input('additional_information'); ?>
    </div>
  </li>
  

  <li class="fields-submit">
    <div class="field-name">
      <span class="required"><?php print _txt('fd.req'); ?></span>
    </div>
    <div class="field-info">
      <?php print $this->Form->submit(_txt('op.submit')); ?>
    </div>
  </li>

</ul>

<?php
  print $this->Form->end();
