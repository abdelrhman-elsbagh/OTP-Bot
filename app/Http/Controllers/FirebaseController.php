<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;

class FirebaseController extends Controller
{
    protected $firestore;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase_credentials.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception("Invalid service account: The file at '$credentialsPath' does not exist");
        }

        if (!is_readable($credentialsPath)) {
            throw new \Exception("Invalid service account: The file at '$credentialsPath' is not readable");
        }

        // Dump the content of the file for debugging
        $credentialsContent = file_get_contents($credentialsPath);
        dump("Credentials File Content: ", $credentialsContent);

        $this->firestore = (new Factory)
            ->withServiceAccount($credentialsPath)
            ->withDatabaseUri(config('firebase.projects.app.database.url'))
            ->createFirestore();
    }

    public function index()
    {
        try {
            $database = $this->firestore->database();
            $collection = $database->collection('users');

            // Get the first document
            $documents = $collection->documents();
            if ($documents->isEmpty()) {
                echo 'No documents found in the collection.';
            } else {
                $firstDocument = $documents->rows()[0];
                echo 'Document ID: ' . $firstDocument->id() . '<br>';
                echo 'Document Data: ' . json_encode($firstDocument->data(), JSON_PRETTY_PRINT);
            }
        } catch (FirebaseException $e) {
            echo 'Firebase Exception: ' . $e->getMessage();
        } catch (AuthException $e) {
            echo 'Auth Exception: ' . $e->getMessage();
        } catch (\Exception $e) {
            echo 'General Exception: ' . $e->getMessage();
        }
    }
}
