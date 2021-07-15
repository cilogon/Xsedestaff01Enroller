<?

class XsedeStaffHomeInstitutionEnum
{
  const Cornell     = 'Cornell';
  const GeorgiaTech = 'GeorgiaTech';
  const Indiana     = 'Indiana';
  const Internet2   = 'Internet2';
  const NCARUCAR    = 'NCARUCAR';
  const NCSA        = 'NCSA';
  const NICS        = 'NICS';
  const OSC         = 'OSC';
  const Oklahoma    = 'Oklahoma';
  const PSC         = 'PSC';
  const Purdue      = 'Purdue';
  const SDSC        = 'SDSC';
  const Shodor      = 'Shodor';
  const SURA        = 'SURA';
  const TACC        = 'TACC';
  const Chicago     = 'Chicago';
  const ISI         = 'ISI';
  const Other       = 'Other';
}

class XsedeStaffComputeAllocationEnum
{
  const TG_ASC160050 = 'TG_ASC160050';
  const TG_ASC160051 = 'TG_ASC160051';
  const TG_ASC170016 = 'TG_ASC170016';
  const TG_ASC170030 = 'TG_ASC170030';
  const TG_ASC170035 = 'TG_ASC170035';
  const TG_CDA170005 = 'TG_CDA170005';
  const TG_DDM16003  = 'TG_DDM16003';
  const TG_IRI160007 = 'TG_IRI160007';
  const TG_STA160002 = 'TG_STA160002';
  const TG_STA160003 = 'TG_STA160003';
  const TG_STA170001 = 'TG_STA170001';
  const TG_TRA160027 = 'TG_TRA160027';

  static function getAllocations() {
    $newClass = new ReflectionClass(__CLASS__);
    return $newClass->getConstants();
  }
}
