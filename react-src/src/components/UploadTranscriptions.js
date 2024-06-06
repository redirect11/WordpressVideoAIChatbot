import { Panel, PanelBody, PanelRow } from '@wordpress/components';
import React, { useState } from 'react';
import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { FormFileUpload } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import TranscriptionsDataView from './Transcriptions/TranscriptionsDataView';


const UploadButton = ( { onClick } ) => {
    return (
        <Button variant="primary" onClick={ onClick } __next40pxDefaultSize>
            { __( 'Upload', 'video-ai-chatbot' ) }
        </Button>
    );
};

const Result = ({ status }) => {
        if (status === "success") {
            return <p>✅ File uploaded successfully!</p>;
        } else if (status === "fail") {
            return <p>❌ File upload failed!</p>;
        } else if (status === "uploading") {
            return <p>⏳ Uploading selected file...</p>;
        } else {
            return null;
        }
};

const UploadTranscriptions = () => {

    const [files, setFiles] = useState([]);
    const [message, setMessage] = useState('');
    const [allTranscriptions, setAllTranscriptions] = useState([]);


    const handleFileChange = (event) => {
        console.log(event.target.files);
        setFiles(event.target.files);

        const transcriptionSize = allTranscriptions.length;

        for (let i = 0; i < event.target.files.length; i++) {
            const file = event.target.files[i];
            const fileReader = new FileReader();
            fileReader.readAsText(file, "UTF-8");
            fileReader.onload = e => {
                const fullTranscription = {
                    file_name: file.name,
                    transcription: JSON.parse(e.target.result),
                    file_id: transcriptionSize + i + 1,
                    assistant_id: [],
                    assistant_name: null,
                };
                console.log('before', allTranscriptions);

                setAllTranscriptions(prevTranscriptions => [...prevTranscriptions, fullTranscription]);

                console.log('after', allTranscriptions);
            };
        }
    };

    const uploadFile = async (transcription) => {
        console.log('uploadFile', transcription);
        const updatedTranscription = JSON.stringify(transcription, null, 2);
        const updatedFile = new Blob([updatedTranscription], { type: "application/json" });
        const updatedFileName = transcription.file_name;

        // Create FormData with the updated file
        const formData = new FormData();
        formData.append('file', new File([updatedFile], updatedFileName));

        const response = await fetch('/wp-json/video-ai-chatbot/v1/upload-transcription/', {
            method: 'POST',
            body: formData,
        });

        if (response.ok) {
            const data = await response.json();
            setMessage(`File uploaded successfully. File ID: ${data.file_id}`);
        } else {
            const errorData = await response.json();
            setMessage(`Error: ${errorData.message}`);
        }
    };
        

    const handleUpload = async () => {
        if (!files || files.length === 0) {
            setMessage('Please select a file and enter an assistant ID.');
            return;
        }

        for (let i = 0; i < allTranscriptions.length; i++) {
            const transcription = allTranscriptions[i];
            await uploadFile(transcription);
        }
    };
    
    const handleSaveTranscription = (newTranscription) => {
        console.log('handleSaveTranscription', newTranscription, newTranscription.file_id);
        const updatedTranscriptions = allTranscriptions.map(transcription => {
            console.log('transcription.file_id', transcription.file_id);
            if(transcription.file_id === newTranscription.file_id) {
                return newTranscription;
            } 
            return transcription;
        });
      
      setAllTranscriptions(updatedTranscriptions);
      setMessage('Transcription saved successfully.');
    };

    const handleDeleteTranscription = (file_id) => {
        const updatedTranscriptions = allTranscriptions.filter(transcription => transcription.file_id !== file_id);
        setAllTranscriptions(updatedTranscriptions);
    };

    return ( 
        <div>
            <Panel>
                <PanelBody>
                    <h3>Carica Trascrizioni</h3>
                    <FormFileUpload
                        multiple={true}
                        accept="application/JSON"
                        onChange={ handleFileChange } 
                        render={ ( { openFileDialog } ) => (
                            <div>
                                <Button variant="secondary" onClick={ openFileDialog }>
                                    Scegli file
                                </Button>
                            </div>
                        )}
                    >
                        { __( 'Carica trascrizione', 'video-ai-chatbot') }

                    </FormFileUpload>
                    {files && files.length > 0 && 
                        <> 
                            <TranscriptionsDataView 
                                transcriptions={allTranscriptions} 
                                onSavingTranscription={handleSaveTranscription} 
                                onDeletingTranscription={handleDeleteTranscription} 
                            />
                            <UploadButton onClick={ () => { handleUpload() } } />
                            {message && <p>{message}</p>}
                        </>
                    }
                </PanelBody>
            </Panel>
        </div>
    );
};

export default UploadTranscriptions;