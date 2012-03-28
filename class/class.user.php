<?php

class user extends baseTable {

  public $primaryKey = "userId";
  public $talbleName = "user"; 
 
  public $selectFields = " user.username, user.password, user.name, user.profileJson ";
  public $select = " FROM user WHERE user.userId > 0 ";
  
  public $validationRules = array(
      "required,username,Полето за име на клиент е задължително",
      "required,password,Полето за парола е задължително"
      );


}