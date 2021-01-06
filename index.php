<?php
$mysqli = new mysqli("localhost", "root", "", "master");
$tableName = 'companies'; // EDIT YOUR TABLE NAME

// Check connection
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

function getFields($table)
{
    global $mysqli;
    $sql = "DESCRIBE $table";
    $result = $mysqli->query($sql);

    $fields = array();
    while ($column = $result->fetch_assoc()) {
        if ($column['Extra'] == 'auto_increment' || $column['Field'] == 'created_at' || $column['Field'] == 'updated_at') {
            continue;
        } else {
            $fields[] = $column;
        }
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


function getFieldType($type) {
    if (strpos($type, 'varchar(') !== false) {
        return ['type' => 'text', 'max' => str_replace(array('varchar(', ')'), '', $type)];
    } else if (strpos($type, 'text') !== false) {
        return ['type' => 'textarea', 'max' => str_replace(array('varchar(', ')'), '', $type)];
    }

    return ['type' => 'text'];
}

function genBootstrapForm($fields) {
    $fieldsText = '';
    foreach ($fields as $field) {
        $fieldsText .= '';
        $type = getFieldType($field['Type']);
        $template = file_get_contents('templates/bootstrap/'.$type['type'].'.txt');
        $fieldCamel = dashesToCamelCase($field['Field']);
        $label = dashesToCamelCase($field['Field'], true, ' ' );
        $template = str_replace('___id___', $field['Field'], $template);
        $template = str_replace('___type___', $type['type'], $template);
        $template = str_replace('___fieldCamel___', $fieldCamel, $template);
        $template = str_replace('___label___', $label, $template);

        $fieldsText .= $template;
    }

    $text = '<form>'.$fieldsText.'<button type="submit" class="btn btn-success col-12">Save</button></form>';
    return $text;
}

$fields = getFields($tableName);

echo '<pre>';
echo htmlspecialchars(genBootstrapForm($fields));
echo '</pre>';
