<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Html\LinkElement;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class CopyrightDao extends Object
{
  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var UploadDao
   */
  private $uploadDao;

  /**
   * @var Logger
   */
  private $logger;

  function __construct(DbManager $dbManager, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->uploadDao = $uploadDao;
    $this->logger = new Logger(self::className());
  }

  /**
   * @param int $uploadTreeId
   * @param string $tableName
   * @param array $typeToHighlightTypeMap
   * @throws \Exception
   * @return Highlight[]
   */
  public function getHighlights($uploadTreeId, $tableName="copyright" ,$typeToHighlightTypeMap=array(
                                                                        'statement' => Highlight::COPYRIGHT,
                                                                        'email' => Highlight::EMAIL,
                                                                        'url' => Highlight::URL)
   )
  {

    $pFileId = 0;
    $row = $this->uploadDao->getUploadEntry($uploadTreeId);

    if (!empty($row['pfile_fk']))
    {
      $pFileId = $row['pfile_fk'];
    } else
    {
      $text = _("Could not locate the corresponding pfile.");
      print $text;
    }

    $statementName = __METHOD__.$tableName;

    $this->dbManager->prepare($statementName,
        "SELECT * FROM $tableName WHERE copy_startbyte IS NOT NULL and pfile_fk=$1");
    $result = $this->dbManager->execute($statementName, array($pFileId));



    $highlights = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      if (!empty($row['copy_startbyte']))
      {
        $type = $row['type'];
        $content = $row['content'];
        // $linkUrl = Traceback_uri() . "?mod=copyright-list&agent=" . $row['agent_fk'] . "&item=$uploadTreeId&hash=" . $row['hash'] . "&type=" . $type;
        // $htmlElement = new LinkElement($linkUrl);
        $htmlElement =null;
        $highlightType = array_key_exists($type, $typeToHighlightTypeMap) ? $typeToHighlightTypeMap[$type] : Highlight::UNDEFINED;
        $highlights[] = new Highlight($row['copy_startbyte'], $row['copy_endbyte'], $highlightType, -1, -1, $content, $htmlElement);
      }
    }
    $this->dbManager->freeResult($result);

    return $highlights;
  }

  public function saveDecision($tableName,$pfileId, $userId , $clearingType,
                                         $description, $textFinding, $comment){
    $statementName = __METHOD__.$tableName;
    $sql = "INSERT INTO $tableName (user_fk, pfile_fk,
           clearing_decision_type_fk, description, textfinding, comment) values ($1,$2,$3,$4,$5,$6)";

    $this->dbManager->getSingleRow($sql,array($userId,$pfileId,
        $clearingType, $description, $textFinding, $comment ),$statementName);
  }

  public function getDecision($tableName,$pfileId){

    $statementName = __METHOD__.$tableName;
    $sql = "SELECT * from $tableName where pfile_fk = $1 order by copyright_decision_pk desc limit 1";

    $res = $this->dbManager->getSingleRow($sql,array($pfileId),$statementName);

    $description = $res['description'];
    $textFinding = $res['textfinding'];
    $comment = $res['comment'];
    $decisionType = $res['clearing_decision_type_fk'];
    return array($description,$textFinding,$comment, $decisionType);
  }
}