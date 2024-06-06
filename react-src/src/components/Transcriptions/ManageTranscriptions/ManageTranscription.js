import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import TranscriptionBaseData from './TranscriptionBaseData';
import TranscriptionTimeline from './TranscriptionTimeline';
import { Button, PanelRow  } from '@wordpress/components'
import AssistantsGrid from './AssistantsGrid';

const SaveButton = ( { onClick } ) => {
    return (
        <Button variant="primary" onClick={ onClick } __next40pxDefaultSize>
            { __( 'Save', 'video-ai-chatbot' ) }
        </Button>
    );
};

const ManageTranscription = ( { selectedTranscription, onTranscriptionSave } ) => {
    const [transcription, setTranscription] = useState(null);

    console.log('selectedTranscription', selectedTranscription);

    useEffect(() => {
        console.log('useEffect selectedTranscription', selectedTranscription);
        setTranscription(selectedTranscription);
    }, [selectedTranscription]);

    const onVideoLinkChanged = (videoLink) => {
        const updatedTranscription = { ...transcription, transcription: {...transcription.transcription, videoHrefLink: videoLink }};
        setTranscription(updatedTranscription);
    };

    const onVideoTitleChanged = (videoTitle) => {
        const updatedTranscription = { ...transcription, transcription: {...transcription.transcription, videoTitle: videoTitle }};
        setTranscription(updatedTranscription);
    };

    const onTextChanged = (transcriptionData) => {
        const updatedTranscription = { ...transcription, transcription: transcriptionData};
        setTranscription(updatedTranscription);
    };

    return (
        <>
            {transcription && 
            <>
                <h2 >Modifica testo trascrizione</h2>
                <PanelRow header="Modifica Trascrizione">
                    <div style={{ width: "100%", height: "100%" }}>
                        <TranscriptionTimeline transcriptionData={transcription.transcription} onTextChanged={onTextChanged} />
                    </div>
                    <div style={{ width: "100%", height: "100%" }}>
                        <TranscriptionBaseData 
                            fileName={transcription.fileName} 
                            fileText={transcription.transcription.videoText} 
                            videoTitle={transcription.transcription.videoTitle} 
                            videoLink={transcription.transcription.videoHrefLink}
                            onVideoLinkChanged={onVideoLinkChanged}
                            onVideoTitleChanged={onVideoTitleChanged}/>
                    </div>
                </PanelRow>
                <br />
                <h2 >Seleziona Assistenti da associare</h2>
                <PanelRow header="Associa Assistenti">
                    <div style={{ width: "100%", height: "100%" }}>
                        <AssistantsGrid transcription={selectedTranscription} onTranscriptionUpdated={setTranscription} />
                    </div>
                </PanelRow>
                <br />
                <SaveButton onClick={ () => { onTranscriptionSave(transcription) } } />
            </>
            }
        </>
    );
};

export default ManageTranscription;