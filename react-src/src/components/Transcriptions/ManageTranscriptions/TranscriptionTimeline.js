import { Timeline, TimelineItem } from 'vertical-timeline-component-for-react';
import React, { useState, useEffect } from 'react';
import { TextControl, TextareaControl, Disabled } from '@wordpress/components';
import './TranscriptionTimeline.css';

const TranscriptionTimeline = ({ transcriptionData, onTextChanged }) => {
    const [transcriptions, setTranscriptions] = useState([]);

    console.log('transcriptionData', transcriptionData);

    useEffect(() => {
        if (transcriptionData && transcriptionData.transcription) {
            setTranscriptions([...transcriptionData.transcription]);
        }
    }, [transcriptionData]);

    const onTextChange = (index, text) => {
        console.log('onTextChange', index, text);
        const updatedTranscriptions = JSON.parse(JSON.stringify(transcriptions));
        updatedTranscriptions[index].text = text;
        const fullText = concatTranscriptions(updatedTranscriptions);
        const newTranscriptionData = { ...transcriptionData, transcription: updatedTranscriptions, videoText: fullText};
        setTranscriptions(newTranscriptionData.transcription);
        onTextChanged(newTranscriptionData);
    }

    const concatTranscriptions = (newTranscriptions) => {
        let fullText = '';
        if (newTranscriptions) {
            newTranscriptions.forEach(transcription => {
                fullText += transcription.text;
            });
        }
        //transcriptionData.videoText = fullText;
        return fullText;
    }

    const truncateText = (text) => {
        return text.substring(0, text.length -4);
    }

    return (
        <div className="scrollable-div">
            <div stlye={{ width: "800px" }}>
                <Timeline lineColor={'#ddd'} >
                    {transcriptions.map((transcription, index) => (
                        <TimelineItem
                            key={index}
                            dateText={`${truncateText(transcription.timestamps.from)} – ${truncateText(transcription.timestamps.to)}`}
                            style={{ color: '#e86971' }}
                        >
                            <TextareaControl 
                                value={transcription.text}
                                onChange={(value) => onTextChange(index, value)} />
                        </TimelineItem>
                    ))}
                </Timeline>
            </div>
        </div>
    );
};

export default TranscriptionTimeline;