import { PanelRow } from '@wordpress/components';
import React, { useEffect, useRef } from 'react';
import { TextControl, TextareaControl, Disabled } from '@wordpress/components';

const TranscriptionBaseData = ({ fileName, 
                                 fileText, 
                                 videoTitle, 
                                 videoLink, 
                                 onVideoTitleChanged, 
                                 onVideoLinkChanged  }) => {

    const textareaRef = useRef();

    useEffect(() => {
        console.log('fileText', fileText);
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
            textareaRef.current.style.height = textareaRef.current.scrollHeight + 'px';
        }
    }, [fileText]);


    return ( 
        <>
            <TextControl
                label="Video Title"
                value={videoTitle}
                onChange={onVideoTitleChanged}/>
            <br />
            <TextControl
                label="Video Link"
                value={videoLink}
                onChange={onVideoLinkChanged}/>
            <br />
            <Disabled> 
                <TextControl
                    label="File Name"
                    value={fileName} 
                    style={ { opacity: 0.5 } }/>
                <br />
                <TextareaControl
                    label="Transcription"
                    ref={textareaRef}
                    value={fileText}
                    onChange={(value) => onTextChanged(value)}
                    style={ { opacity: 0.5 } } />
            </Disabled>
        </>
    );
};

export default TranscriptionBaseData;