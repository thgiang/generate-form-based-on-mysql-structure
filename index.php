<?php
require_once 'ggsheet.php';

$dbName = "YOUR_DATABASE_NAME";
$googleSheetId = '1aw2J2QSz8NoCgjKQ8wsTHP_Xkv345gsKCAp3_YOUR_GOOGLE_SHEET'; // Dont forget create google service account and share edit permission for the account.

$mysqli = new mysqli("YOUR_DB_HOST", "YOUR_DB_USERNAME", "YOUR_DB_PASSWORD", $dbName);

// Check connection
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

function getTables()
{
    global $dbName;
    global $mysqli;
    $sql = "SHOW TABLES";
    $result = $mysqli->query($sql);

    $tables = array();
    while ($column = $result->fetch_assoc()) {

        $tables[] = array_values($column)[0];

    }

    return $tables;
}

function getFields($table)
{
    global $mysqli;
    $sql = "DESCRIBE $table";
    $result = $mysqli->query($sql);

    $fields = array();
    while ($column = $result->fetch_assoc()) {
        $fields[] = $column;
    }

    return $fields;
}

function dashesToCamelCase($string, $capitalizeFirstCharacter = false, $space = '')
{

    $str = str_replace(' ', $space, ucwords(str_replace('_', ' ', $string)));

    if (!$capitalizeFirstCharacter) {
        $str[0] = strtolower($str[0]);
    }

    return $str;
}


function getFieldType($type)
{
    if (strpos($type, 'varchar(') !== false) {
        return ['type' => 'text', 'max' => str_replace(array('varchar(', ')'), '', $type)];
    } else if (strpos($type, 'text') !== false) {
        return ['type' => 'textarea', 'max' => str_replace(array('varchar(', ')'), '', $type)];
    }

    return ['type' => 'text'];
}

function genBootstrapForm($fields)
{
    $fieldsText = '';
    foreach ($fields as $field) {
        $fieldsText .= '';
        $type = getFieldType($field['Type']);
        $template = file_get_contents('templates/bootstrap/' . $type['type'] . '.txt');
        $fieldCamel = dashesToCamelCase($field['Field']);
        $label = dashesToCamelCase($field['Field'], true, ' ');
        $template = str_replace('___id___', $field['Field'], $template);
        $template = str_replace('___type___', $type['type'], $template);
        $template = str_replace('___fieldCamel___', $fieldCamel, $template);
        $template = str_replace('___label___', $label, $template);

        $fieldsText .= $template;
    }

    $text = '<form>' . $fieldsText . '<button type="submit" class="btn btn-success col-12">Save</button></form>';
    return $text;
}

function genTableForm($fields)
{
    $fieldsText = '';
    foreach ($fields as $field) {
        $fieldsText .= '';
        $type = $field['Type'];
        $template = file_get_contents('templates/table/tr.txt');
        $fieldCamel = dashesToCamelCase($field['Field']);
        $label = dashesToCamelCase($field['Field'], true, ' ');
        $template = str_replace('___id___', $field['Field'], $template);
        $template = str_replace('___type___', $type, $template);
        $template = str_replace('___fieldCamel___', $fieldCamel, $template);
        $template = str_replace('___label___', $label, $template);

        $fieldsText .= $template;
    }

    $text = '<table border="1">' . $fieldsText . '</table>';
    return $text;
}

function generateMigrationFile($tableName, $fields)
{
    global $dbName;
    $milliseconds = floor(microtime(true) * 1000);
    $header = file_get_contents('templates/migration/header.txt');
    $footer = file_get_contents('templates/migration/footer.txt');
    $className = 'addCommentForTable' . ucfirst(dashesToCamelCase($tableName)) . $milliseconds;
    $fileName = $milliseconds . '-add-comment-for-' . $tableName;
    $header = str_replace('___className___', $className, $header);
    $rowTemplate = file_get_contents('templates/migration/template.txt');
    $text = $header;
    foreach ($fields as $field) {
        $comment = str_replace('_', ' ', ucfirst($field['Field']));
        $rowText = str_replace('___tableName___', $tableName, $rowTemplate);
        $rowText = str_replace('___type___', strtoupper($field['Type']), $rowText);
        $rowText = str_replace('___fieldName___', $field['Field'], $rowText);
        $rowText = str_replace('___comment___', $comment, $rowText);

        $text .= $rowText;
    }
    $text .= $footer;


    file_put_contents($dbName . DIRECTORY_SEPARATOR . $fileName . '.ts', $text);
    return $text;
}

function genMigrateFile()
{
    global $dbName;

    if (!is_dir($dbName)) {
        mkdir($dbName);
    }
    $tables = getTables();
    foreach ($tables as $table) {
        $fields = getFields($table);
        generateMigrationFile($table, $fields);
        echo "Created migration file for table <strong>$table</strong><br>\n";
    }
}

/**
 * @throws Google_Exception
 */
function pushToGG()
{
    global $googleSheetId;
    $ggSheet = new GGSheet($googleSheetId);
    $sheets = $ggSheet->getSheets();
    $tables = getTables();
    foreach ($tables as $table) {
        $sheetName = $table;
        try {
            $rows = $ggSheet->readOrCreateSheet($sheetName . '!A1:F100');
        } catch (Google_Exception $e) {
            echo $e->getMessage();
            exit;
        }
        $existingNotes = [];
        foreach ($rows as $line => $row) {
            if ($line == 0) {
                continue;
            }
            $colName = $row[1];
            $colNote = $row[5];
            if (!empty($colName) && !empty($colNote)) {
                $existingNotes[$sheetName . '__' . $colName] = $colNote;
            }
        }

        $newData = [];
        $newData[] = ['No', 'Column name', 'Column type', 'Nullable', 'Column Description (EN)', 'Column Description (JP)', 'Note'];
        $fields = getFields($table);
        foreach ($fields as $i => $field) {
            $eng = str_replace('_', ' ', ucfirst($field['Field']));
            $newData[] = [($i + 1), $field['Field'], $field['Type'], $field['Null'],  $eng, '', $field['Comment']];
        }

        foreach ($newData as $k => &$newDatum) {
            if (empty($newDatum[6]) && !empty($existingNotes[$k])) {
                $newDatum[6] = $existingNotes[$k];
            }
        }
        $ggSheet->writeRange($sheetName . '!A1:G', array_values($newData));

    }

    $sheets = $ggSheet->getSheets();
    foreach ($tables as $table) {
        if (isset($sheets[$table])) {
            $ggSheet->beautifulFormat($sheets[$table], 0, 0, 1, 7);
        }
    }
}

pushToGG();