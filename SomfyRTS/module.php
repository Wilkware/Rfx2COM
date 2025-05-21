<?php

declare(strict_types=1);

// General functions
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS Somfy RTS
 */
class SomfyRTS extends IPSModule
{
    use DebugHelper;
    use ProfileHelper;
    use VariableHelper;

    // Min IPS Object ID
    private const IPS_MIN_ID = 10000;

    // Data flow IDs
    private const GUID_SIMPLE_IO = '{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}';  // I/O instance
    private const GUID_SIMPLE_RX = '{018EF6B5-AB94-40C6-AA53-46943E824ACF}';  // from port to module
    private const GUID_SIMPLE_TX = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';  // from module to port

    // Profiles
    private const PROFILE_TYPES = [
        0 => 'R2C.Awning',
        1 => 'R2C.Valance',
    ];

    // RTXCOM
    private const RTY_PREFIX = "\x0C\x1A";
    private const RTY_SUFFIX = "\x00\x00\x00\x00";

    // Unit Type
    private const UNIT_TYPE = [
        0 => '00',
        3 => '03',
    ];

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Unit
        $this->RegisterPropertyInteger('UnitType', 0);
        $this->RegisterPropertyString('UnitID', '000000');
        $this->RegisterPropertyString('UnitCode', '01');
        // Visu
        $this->RegisterPropertyInteger('VisuType', 0);
        $this->RegisterPropertyString('VisuPosition', 'right');
        $this->RegisterPropertyInteger('VisuVariable', 1);
        $this->RegisterPropertyInteger('VisuInColor', -1);
        $this->RegisterPropertyInteger('VisuOutColor', -1);

        // I/O Instance
        $this->RequireParent(self::GUID_SIMPLE_IO);

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(1);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     */
    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Unregister reference
        foreach ($this->GetReferenceList() as $id) {
            $this->UnregisterReference($id);
        }
        // Unregister all messages
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Profile "R2C.Awning"
        $association = [
            [0, 'My/Stop', 'square-m', -1],
            [1, 'In', 'square-arrow-left', -1],
            [3, 'Out', 'square-arrow-right', -1],
        ];
        $this->RegisterProfileInteger(self::PROFILE_TYPES[0], '', '', '', 0, 0, 0, $association);
        // Profile "R2C.Valance"
        $association = [
            [0, 'My/Stop', 'square-m', -1],
            [1, 'Up', 'square-arrow-up', -1],
            [3, 'Down', 'square-arrow-down', -1],
        ];
        $this->RegisterProfileInteger(self::PROFILE_TYPES[1], '', '', '', 0, 0, 0, $association);

        // Setup infos
        $type = $this->ReadPropertyInteger('VisuType');

        // Maintain variables
        $this->MaintainVariable('Remote', $this->Translate('Remote Control'), 1, self::PROFILE_TYPES[$type], 1, true);
        $this->MaintainAction('Remote', true);

        $variable = $this->ReadPropertyInteger('VisuVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
            $this->RegisterMessage($variable, VM_UPDATE);
        }
        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * This function is called by IP-Symcon and processes sent data and, if necessary, forwards it to all child instances.
     *
     *      Response = ACK,
     *          Raw data = 0402010100
     *
     * @param string $json Data package in JSON format
     */
    public function ReceiveData($json)
    {
        $data = json_decode($json);
        $this->SendDebug(__FUNCTION__, utf8_decode($data->Buffer), 0);
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     *  @param string $ident Ident of the variable
     *  @param string $value The value to be set
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'Remote':
                $this->HandleCommand($value);
                break;
        }
        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return String Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile()
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Return everything
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $initialHandling;
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param mixed $timestamp Continuous counter timestamp
     * @param mixed $sender Sender ID
     * @param mixed $message ID of the message
     * @param mixed $data Data of the message
     */
    public function MessageSink($timestamp, $sender, $message, $data)
    {
        // Debug
        // $this->SendDebug(__FUNCTION__, 'SenderId: ' . $sender . ' Data: ' . $this->DebugPrint($data), 0);
        // React to updates
        if ($message == VM_UPDATE) {
            // only if values changed!
            if ($data[1] == true) {
                // Dark Mode activation
                if ($this->ReadPropertyInteger('VisuVariable') == $sender) {
                    // Parts of the HTML display the new state with
                    //$this->UpdateVisualizationValue(json_encode(['status' => $data[0]]));
                    // Send a complete update message to the display, as parameters may have changed
                    $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                }
            }
        }
    }

    /**
     * Send data to I/O interface
     *
     * 0C1A 00 01 010203 01 03 00000000
     * ---- -- -- ------ -- -- --------
     * |    |  |  |      |  |  |======= 8 x zero
     * |    |  |  |      |  |========== command: STOP(00), UP(01), DOWN(03)
     * |    |  |  |      |============= unit code 00 to FF
     * |    |  |  |==================== unit id (000001 to FFFFFF)
     * |    |  |======================= sequence number (01)
     * |    |========================== subtype (00 = Somfy RTS(RFY), 01 = RFY-EXT, 03 = ASA)
     * |=============================== prefix (0C1A)
     *
     * @param string $text sequence of unit and command
     * @return String HTML coded color or empty string
     */
    private function SendData(string $text)
    {
        $resultPort = true;
        // Serial Port
        $simple['DataID'] = self::GUID_SIMPLE_TX;
        $simple['Buffer'] = self::RTY_PREFIX . $text . self::RTY_SUFFIX;
        $json = json_encode($simple, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__, $json, 0);
        $resultPort = @$this->SendDataToParent($json);
        return $resultPort;
    }

    /**
     * Transform the passed command to a full sequence and send the data
     *
     * @param int $command Command to execute
     */
    private function HandleCommand(int $command)
    {
        $type = $this->ReadPropertyInteger('UnitType');
        $id = $this->ReadPropertyString('UnitID');
        $code = $this->ReadPropertyString('UnitCode');

        // $type → chr() with leading zero
        $output = chr($type); // corresponds chr(0x00)
        // $sequence → chr() fixed one with leading zero
        $output .= chr(1); // corresponds chr(0x01)
        // $code → 6 digits in 3 bytes
        $output .= hex2bin($id);
        // $id → 2-digit in 1 byte
        $output .= hex2bin($code);
        // $command → chr() with leading zero
        $output .= chr($command);
        // output as hex string for checking
        $this->SendDebug(__FUNCTION__, bin2hex($output), 0);
        // send data
        $this->SendData($output);
        // set status variable
        $this->SetValueInteger('Remote', $command);
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return String JSON encoded message information
     */
    private function GetFullUpdateMessage()
    {
        // dataset variable
        $remote = match ($this->GetValue('Remote')) {
            0 => 'half',
            1 => 'none',
            3 => 'full',
        };
        $status = 'in';
        $vid = $this->ReadPropertyInteger('VisuVariable');
        if ($vid >= self::IPS_MIN_ID) {
            if (!GetValue($vid)) {
                $status = 'out';
            }
        }
        $type = $this->ReadPropertyInteger('VisuType');
        $position = $this->ReadPropertyString('VisuPosition');
        $in = $this->GetColorFormatted($this->ReadPropertyInteger('VisuInColor'));
        $out = $this->GetColorFormatted($this->ReadPropertyInteger('VisuOutColor'));

        // Data
        $result = [
            'status'    => ($status == 'in' ? $in : $out),
            'type'      => ($type == 0 ? 'awning' : 'valance'),
            'align'     => ($position),
            'remote'    => ($remote),
        ];
        //$this->SendDebug(__FUNCTION__, $result, 0);
        return json_encode($result);
    }

    /**
     * Get HTML rgb formated color.
     *
     * @param int $color Color value or -1 for transparency
     * @return String HTML coded color or empty string
     */
    private function GetColorFormatted(int $color)
    {
        if ($color != '-1') {
            return '#' . sprintf('%06X', $color);
        } else {
            return '';
        }
    }
}
