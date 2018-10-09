<?php
/**
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use Doctrine\DBAL\Connection;
use PDO;
use Symfony\Component\Console\Output\OutputInterface;

class Gateway
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $dbal;

    public function __construct(Connection $dbal)
    {
        $this->dbal = $dbal;
    }

    public function getContentTypeIds($contentTypeIdentifiers)
    {
        $query = $this->dbal->createQueryBuilder();

        $query->select('c.identifier, c.id')
            ->from('ezcontentclass', 'c')
            ->where(
                $query->expr()->in(
                    'c.identifier',
                    ':contentTypeIdentifiers'
                )
            )
            ->setParameter(':contentTypeIdentifiers', $contentTypeIdentifiers, Connection::PARAM_STR_ARRAY);

        $statement = $query->execute();

        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($columns as $column) {
            $result[$column['identifier']] = $column['id'];
        }

        return $result;
    }

    public function convertFieldDefinitions($dryRun, OutputInterface $output)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentclass_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring', 'ezxmltext');

        $statement = $query->execute();
        $count = (int) $statement->fetchColumn();

        $output->writeln("Found $count field definiton to convert.");

        $updateQuery = $this->dbal->createQueryBuilder();
        $updateQuery->update('ezcontentclass_attribute')
            ->set('data_type_string', ':newdatatypestring')
            // was tagPreset in ezxmltext, unused in RichText
            ->set('data_text2', ':datatext2')
            ->where(
                $updateQuery->expr()->eq(
                    'data_type_string',
                    ':olddatatypestring'
                )
            )
            ->setParameters([
                ':newdatatypestring' => 'ezrichtext',
                ':datatext2' => null,
                ':olddatatypestring' => 'ezxmltext',
            ]);

        if (!$dryRun) {
            $updateQuery->execute();
        }

        $output->writeln("Converted $count ezxmltext field definitions to ezrichtext");
    }

    public function getRowCountOfContentObjectAttributes($datatypeString, $contentId)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring', $datatypeString);

        if ($contentId !== null) {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentid'
                )
            )
                ->setParameter(':contentid', $contentId);
        }

        $statement = $query->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Get the specified field rows.
     * Note that if $contentId !== null, then $offset and $limit will be ignored.
     *
     * @param $datatypeString
     * @param $contentId
     * @param $offset
     * @param $limit
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    public function getFieldRows($datatypeString, $contentId, $offset, $limit)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('a.*')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->orderBy('a.id')
            ->setParameter(':datatypestring', $datatypeString);

        if ($contentId === null) {
            $query->setFirstResult($offset)
                ->setMaxResults($limit);
        } else {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentid'
                )
            )
                ->setParameter(':contentid', $contentId);
        }

        return $query->execute();
    }

    public function updateFieldRow($dryRun, $id, $version, $datatext)
    {
        $updateQuery = $this->dbal->createQueryBuilder();
        $updateQuery->update('ezcontentobject_attribute')
            ->set('data_type_string', ':datatypestring')
            ->set('data_text', ':datatext')
            ->where(
                $updateQuery->expr()->eq(
                    'id',
                    ':id'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'version',
                    ':version'
                )
            )
            ->setParameters([
                ':datatypestring' => 'ezrichtext',
                ':datatext' => $datatext,
                ':id' => $id,
                ':version' => $version,
            ]);

        if (!$dryRun) {
            $updateQuery->execute();
        }
    }

    public function contentObjectAttributeExists($objectId, $attributeId, $version, $language)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':objectid'
                )
            )
            ->andWhere(
                $query->expr()->eq(
                    'a.id',
                    ':attributeid'
                )
            )
            ->andWhere(
                $query->expr()->eq(
                    'a.version',
                    ':version'
                )
            )
            ->andWhere(
                $query->expr()->eq(
                    'a.language_code',
                    ':language'
                )
            )
            ->setParameter(':datatypestring', 'ezxmltext')
            ->setParameter(':objectid', $objectId)
            ->setParameter(':attributeid', $attributeId)
            ->setParameter(':version', $version)
            ->setParameter(':language', $language);

        $statement = $query->execute();
        $count = (int)$statement->fetchColumn();

        return $count === 1;
    }

    public function updateContentObjectAttribute($xml, $objectId, $attributeId, $version, $language)
    {
        $updateQuery = $this->dbal->createQueryBuilder();
        $updateQuery->update('ezcontentobject_attribute')
            ->set('data_text', ':newxml')
            ->where(
                $updateQuery->expr()->eq(
                    'data_type_string',
                    ':datatypestring'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'contentobject_id',
                    ':objectid'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'id',
                    ':attributeid'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'version',
                    ':version'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'language_code',
                    ':language'
                )
            )
            ->setParameter(':newxml', $xml)
            ->setParameter(':datatypestring', 'ezxmltext')
            ->setParameter(':objectid', $objectId)
            ->setParameter(':attributeid', $attributeId)
            ->setParameter(':version', $version)
            ->setParameter(':language', $language);
        $updateQuery->execute();
    }
}
