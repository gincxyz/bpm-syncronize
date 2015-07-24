<?php
/**
 * Класс для синхронизации PHP приложений с BPM Online по протоколу OData
 * Примеры использования:
 *      use Ginc\Syncronize;
 *      $sync = new Syncronize();
 *      //Получение всей колекции объектов
 *      $sync->collection('Тип_объекта');
 *      //Добавление объекта
 *      $sync->add('Тип_объекта', массив_параметров);
 *      //Получение объекта по GUID
 *      $sync->show('GUID_объекта', 'Тип_объекта');
 *      //Изменение объекта по GUID и типу
 *      $sync->edit('GUID_объекта', 'Тип_объекта', 'массив_данных');
 *      //Удаление объекта по GUID и типу
 *      $sync->delete('GUID_объекта', 'Тип_объекта');
 */
namespace Ginc;


class Syncronize {

    /**
     * Параметры настройки
     *
     * @link string адрес расположения BPM Online
     * @account string имя пользователя и пароль для доступа
     */
    private $link = 'http://your_link/0/ServiceModel/EntityDataService.svc/';
    private $account = 'Supervisor:Supervisor';

    /**
     * Отправка cURL запроса в BPM Online OData
     *
     * @param $method string метод запроса (GET|POST|PUT|DELETE)
     * @param $link string ссылка на которую будет сделать запрос
     * @param $data string xml данные при добавлении/редактировании объекта
     * @return string xml ответ BPM Online OData в формате xml
     */
    private function request($method, $link, $data = null) {
        $link = $this->link.$link;
        $ch = curl_init($link);
        $options = array(
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => array(
                'MaxDataServiceVersion: 3.0',
                'Content-Type: application/json;odata=verbose',
                'DataServiceVersion: 1.0',
                'Accept: application/json;odata=verbose',
                'Authorization: Basic '.base64_encode($this->account))
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result)->d;
    }

    /**
     * Получение биннарных файлов прикрепленных к объекту
     *
     * @param $id string ID объекта
     * @param $object string название объекта
     * @return string возвращает файл закодированный base64
     */
    public function files($id, $object){
        $result = array();
        $files = $this->request('GET', $object.'FileCollection/?$filter='.$object.'/Id%20eq%20guid%27'.$id.'%27')->results;
        foreach ($files as $file) {
            $ch = curl_init($file->Data->__mediaresource->edit_media);
            $options = array(
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_HTTPHEADER     => array(
                    'MaxDataServiceVersion: 3.0',
                    'Content-Type: application/json;odata=verbose',
                    'DataServiceVersion: 1.0',
                    'Authorization: Basic '.base64_encode($this->account))
            );
            curl_setopt_array($ch, $options);
            $result[] = curl_exec($ch);
            curl_close($ch);
        }
        return $result;
    }

    /**
     * Загрузка изображения в BPM Online OData
     *
     * @param $guid string guid изображения которому загружать бинарные данные
     * @param $file string бинарные данные файла
     * @return string
     */
    public function upload($link, $guid, $file) {
        if ($link == 'SysImage') $link = $this->link.urlencode($link."Collection(guid'$guid')").'/PreviewData';
        else $link = $this->link.urlencode($link."Collection(guid'$guid')").'/Data';
        $ch = curl_init($link);
        $options = array(
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POSTFIELDS     => $file,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: multipart/form-data;boundary=+++++',
                'Authorization: Basic '.base64_encode($this->account))
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * Метод для получения коллекции объектов определенного типа, с возможностью задать фильтр
     *
     * @param string $object название объектов которые необходимо получить
     * @param string $filter фильтр выборки объектов
     * @return stdClass
     */
    public function collection($object, $filter = null)
    {
        return $this->request('GET', $object."Collection".$filter)->results;
    }

    /**
     * Метод для добавляения объекта, возвращает созданный объект
     *
     * @param string $object название объекта
     * @param array $data массив с данными для добавления
     * @return stdClass созданный объект
     */
    public function add($object, array $data)
    {
        return $this->request('POST', $object.'Collection', json_encode($data));
    }

    /**
     * Метод для получения объекта по его GUID и типу в базе BPM Online
     *
     * @param $id string guid в базе BPM Online
     * @param $object string тип объекта
     * @return stdClass объект
     */
    public function show($id, $object)
    {
        return $this->request('GET', $object."Collection(guid'$id')");
    }

    /**
     * Метод для изменения объекта по его GUID и типу в базе BPM Online
     *
     * @param string $guid объекта
     * @param string $object название объекта
     * @param array $data массив с данными для добавления
     */
    public function edit($guid, $object, array $data)
    {
        $this->request('PUT', $object.'Collection('.urlencode("guid'$guid'").')', json_encode($data));
    }

    /**
     * Метод для удаления объекта по его GUID и типу в базе BPM Online
     *
     * @param string $guid объекта
     * @param string $object название объекта
     */
    public function delete($guid, $object)
    {
        $this->request('DELETE', $object.'Collection('.urlencode("guid'$guid'").')');
    }
    
}