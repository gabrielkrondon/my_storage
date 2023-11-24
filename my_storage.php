<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php");

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use \Aws\S3\Transfer;
use Aws\S3\ObjectUploader;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

define("BUCKET_REGION", "");
define("BUCKET_NAME", "");
define("BUCKET_KEY", "");
define("BUCKET_SECRET", "");

class my_storage
{
    /**
     * Função construtora cria uma instância de s3 Client
     * para realizar as comunicações com o bucket
     *
     */
    public function __construct()
    {
        try {
            $this->s3Client = new Aws\S3\S3Client(
                [
                    'region' => BUCKET_REGION,
                    'version' => 'latest',
                    'credentials' =>
                        [
                            'key' => BUCKET_KEY,
                            'secret' => BUCKET_SECRET,
                        ]
                ]
            );
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }

    }

    /**
     * Recebe o path de destino e o conteudo do arquivo
     * e realiza a gravação do arquivo no caminho informado
     *
     * @param string $file_path
     * @param string $file_content
     * @param array $args
     *
     * @return \Aws\Result|false
     */
    public function put($key, $file_content, $public = false)
    {
        if (empty($file_content)) {
            return false;
        } else {
            try {
                if ($public) {
                    $config = [
                        'Bucket' => BUCKET_NAME,
                        'Key' => $key,
                        'Body' => $file_content,
                        'ACL' => 'public-read'
                    ];
                } else {
                    $config = [
                        'Bucket' => BUCKET_NAME,
                        'Key' => $key,
                        'Body' => $file_content,
                    ];
                }
                $response = $this->s3Client->putObject($config);
                return $response['ObjectURL'] . PHP_EOL;
            } catch (S3Exception $e) {
                return $e->getAwsErrorMessage() . "\n";
                return false;
            }
        }

    }

    /**
     * Recebe o Path de destino e o path absoluto do arquivo local
     * e grava o arquivo no Path de destino
     *
     * @param string $destination_path
     * @param string $file_path
     *
     * * @return \Aws\Result|false
     */
    public function storage_from_local($destination_path, $file_path)
    {
        try {
            $response = $this->s3Client->putObject([
                'Bucket' => BUCKET_NAME,
                'Key' => $destination_path,
                'SourceFile' => $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path,
                'ACL' => 'public-read'
            ]);
            return $response;
        } catch (S3Exception $e) {
            return $e->getMessage() . PHP_EOL;
        }

        return false;
    }

    /**
     * Recebe um Path e retorna o conteudo de um arquivo
     *
     * @param string $file_path
     *
     * @return string|false
     */
    public function get($file_path)
    {
        if (CLOUD) {
            try {
                $result = $this->s3Client->getObject([
                    'Bucket' => BUCKET_NAME,
                    'Key' => $file_path
                ]);

                return $result['Body'];
            } catch (S3Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        } else {
            return file_get_contents($_SERVER['DOCUMENT_ROOT'] . $file_path);
        }
        return false;
    }

    /**
     * Recebe o Path(key) e retorna o download de um arquivo
     *
     * @param string $key
     *
     * @return string|false
     */
    public function download($key)
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => BUCKET_NAME,
                'Key' => $key
            ]);

            header("Content-Type: {$result['ContentType']}");
            return $result['Body'];

        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * Recebe o Path do arquivo e retorna sua URL
     *
     * @param string $file_path
     *
     * @return string|false
     */
    public function url($file_path)
    {
        if (empty($file_path)) return null;
        try {
            return $this->s3Client->getObjectUrl(BUCKET_NAME, $file_path);
        } catch (S3Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        return false;
    }

    /**
     * Checa se o arquivo existe
     * @param string $file_path
     *
     * @return true|false
     */
    public function exists($file_path)
    {
        try {
            $response = $this->s3Client->doesObjectExist(BUCKET_NAME, $file_path);
            if ($response) {
                return true;
            }

        } catch (S3Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        return false;
    }

    /**
     * Checa se o arquivo existe
     *
     * @return array|false
     */
    public function listDrivers()
    {
        try {
            return $this->s3Client->listBuckets();
        } catch (S3Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        return false;
    }

    /**
     * Retorna Array com as Keys dos arquivos
     *
     * @return Array|false
     */
    public function files()
    {
        $chaves = [];
        try {
            $results = $this->s3Client->getPaginator('ListObjects', [
                'Bucket' => BUCKET_NAME
            ]);

            foreach ($results as $result) {
                foreach ($result['Contents'] as $object) {
                    $chaves[] = $object['Key'] . PHP_EOL;
                }
            }

            return $chaves;
        } catch (S3Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        return false;
    }

    public function multipart_upload($key, $file_path)
    {
        $uploader = new MultipartUploader($this->s3Client, $file_path, [
            'bucket' => BUCKET_NAME,
            'key' => $key,
        ]);

        try {
            $result = $uploader->upload();

            return true;
        } catch (MultipartUploadException $e) {
            return $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * @param $s3_source
     * @param $dest
     * Recebe uma key do s3 e um destino local ex:'/path/to/destination/dir'
     *
     */
    public function transfer($s3_source, $local_dest)
    {
        if (empty($s3_source) || empty($local_dest)) return null;

        $s3_source = 's3://' . BUCKET_NAME . '/' . $s3_source;
        $manager = new \Aws\S3\Transfer($this->s3Client, $s3_source, $local_dest);
        $promise = $manager->promise();
        $promise->then(function () {
            return true;
        });
        $promise->otherwise(function ($reason) {
            echo 'Transfer failed: ';
            var_dump($reason);
        });
    }

    public function delete($key)
    {
        if (empty($key)) return null;

        return $this->s3Client->deleteObject($key);
    }

}
