<?php
error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/vendor/autoload.php';

class GGSheet
{
    public $client;
    public $service;
    public $fileId;

    /**
     * @throws Google_Exception
     */
    function __construct($fileId = '1VSJZIosi0EjHCvLL9YgYywaTn7TOECfKlRcPQrdxRws')
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName("Google Sheets API PHP Quickstart");
        $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $this->client->setAuthConfig(__DIR__ . '/credentials.json');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->service = new Google_Service_Sheets($this->client);
        $this->fileId = $fileId;
    }

    function getSheets()
    {
        $sheets = array();
        $sheetInfo = $this->service->spreadsheets->get($this->fileId);
        $sheetInfos = $sheetInfo['sheets'];
        foreach ($sheetInfos as $item) {
            $sheets[$item->properties->title] = $item->properties->sheetId;
        }
        return $sheets;
    }

    /**
     * @throws Exception
     */
    function readRange($range = 'Class Data!A2:E')
    {
        try {
            $response = $this->service->spreadsheets_values->get($this->fileId, $range);
            return $response->getValues();
        } catch (Google_Service_Exception $e) {
            echo $e->getMessage();
            throw new Exception($e);
        }
    }

    /**
     * @throws Exception
     */
    function createNewSheet($sheetName)
    {
        try {
            $body = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
                'requests' => array(
                    'addSheet' => array(
                        'properties' => array(
                            'title' => $sheetName
                        )
                    )
                )
            ));
            return $this->service->spreadsheets->batchUpdate($this->fileId, $body);
        } catch (Exception $ignore) {
            throw new Exception($ignore);
        }
    }

    /**
     * @throws Exception
     */
    function readOrCreateSheet($range)
    {
        try {
            $rows = $this->readRange($range);
        } catch (Exception $e) {
            $sheetName = explode('!', $range)[0];
            $this->createNewSheet($sheetName);
            $rows = $this->readOrCreateSheet($range);
        }
        return $rows;
    }

    /**
     * @throws Exception
     */
    function writeRange($range, $values)
    {
        try {
            $body = new Google_Service_Sheets_ValueRange(array(
                'values' => $values
            ));
            $params = array(
                'valueInputOption' => 'RAW'
            );
            return $this->service->spreadsheets_values->update($this->fileId, $range, $body, $params);
        } catch (Exception $ignore) {
            throw new Exception($ignore);
        }
    }

    function beautifulFormat($sheetId, $startRow, $startColumn, $endRow, $endColumn)
    {
        $r = 1;
        $g = 0;
        $b = 0.6;
        $a = 1;

        $requestBody = [
            'requests' => [
                "autoResizeDimensions" => [
                    "dimensions" => [
                        "sheetId" => $sheetId,
                        "dimension" => "COLUMNS",
                        "startIndex" => 0 ,
                        "endIndex" => 7
                    ]
                ],
            ],
        ];

        $request = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest($requestBody);
        $this->service->spreadsheets->batchUpdate($this->fileId, $request);

        $myRange = [
            'sheetId' => $sheetId, // IMPORTANT: sheetId IS NOT the sheets index but its actual ID
            'startRowIndex' => $startRow,
            'endRowIndex' => $endRow,
            'startColumnIndex' => $startColumn,
            'endColumnIndex' => $endColumn,
        ];

        $format = [
            'backgroundColor' => [
                'red' => $r,
                'green' => $g,
                'blue' => $b,
                'alpha' => $a,
            ],
            'textFormat' => [
                'bold' => true
            ]
        ];

        $requests = [
            new Google_Service_Sheets_Request([
                'repeatCell' => [
                    'fields' => 'userEnteredFormat.backgroundColor, userEnteredFormat.textFormat.bold',
                    'range' => $myRange,
                    'cell' => ['userEnteredFormat' => $format],
                ],
            ])
        ];

        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => $requests]);

        return $this->service->spreadsheets->batchUpdate($this->fileId, $batchUpdateRequest);
    }
}