# Generate Bootstrap form based on mysql structure.
Open index.php, edit your database connect information
```
$mysqli = new mysqli("localhost", "root", "", "master");
$tableName = 'companies'; // EDIT YOUR TABLE NAME
```
Run http://localhost/generate-from-based-on-mysql-structe/index.php
