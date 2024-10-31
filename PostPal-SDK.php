<?php

/**
 * Created by PostPal <www.postpal.eu>
 * User: Klemens Arro
 * Date: 14.02.16
 * Time: 15:53
 */
class PostPal_SDK
{
    private $APIKey = null;
    private $warehouseCode = null;
    private $price = null;
    private $API_URL = 'https://my.postpal.ee/api/shop/v1';
    private $version = '0.1';
    private $module = '';

    public function setAPIKey($APIKey)
    {
        $this->APIKey = $APIKey;
    }

    public function setWarehouseCode($warehouseCode)
    {
        $this->warehouseCode = $warehouseCode;
    }

    public function setPrice($price)
    {
        $this->price = $price;
    }

    public function setModule($module)
    {
        $this->module = $module . ';';
    }

    public function estimation()
    {
        $response = $this->request('estimation', array(
            'warehouse' => $this->warehouseCode
        ));

        return $response;
    }

    public function validate($data)
    {
        $response = $this->request('orders/new/warehouse/validate', array(
            'warehouse' => $this->warehouseCode,
            'destinationFirstName' => $data['firstName'],
            'destinationLastName' => $data['lastName'],
            'destinationCompany' => $data['company'],
            'destinationEmail' => $data['email'],
            'destinationApartment' => $data['apartment'],
            'destinationAddress' => $data['address'],
            'destinationLocality' => $data['locality'],
            'destinationCountry' => $data['country'],
            'destinationPostalCode' => $data['postalCode'],
            'destinationPhone' => $data['phone'],
            'notes' => $data['notes'],
            'packageSize' => $data['packageSize']
        ));

        return $response;
    }

    public function order($data)
    {
        $response = $this->request('orders/new/warehouse', array(
            'warehouse' => $this->warehouseCode,
            'destinationFirstName' => $data['firstName'],
            'destinationLastName' => $data['lastName'],
            'destinationCompany' => $data['company'],
            'destinationEmail' => $data['email'],
            'destinationApartment' => $data['apartment'],
            'destinationAddress' => $data['address'],
            'destinationLocality' => $data['locality'],
            'destinationCountry' => $data['country'],
            'destinationPostalCode' => $data['postalCode'],
            'destinationPhone' => $data['phone'],
            'notes' => $data['notes'],
            'packageSize' => $data['packageSize']
        ));

        return $response;
    }

    private function request($request, $params = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->API_URL . '/' . $request);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PostPal SDK (' . $this->module . 'version ' . $this->version . ')');
        curl_setopt($ch, CURLOPT_POST, 1);

        $data = array(
            'token' => $this->APIKey
        );

        if($params != null)
            $data = array_merge($data, $params);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($output, $headerSize);

        if($httpCode != '200' && $httpCode != '422')
            return array('error' => 'PostPal shipping is not configured correctly');

        curl_close($ch);

        return json_decode($body);
    }
}