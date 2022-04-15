import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element'; // this is the abstraction for react?
import { TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import Form from 'react-bootstrap/Form';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import { Formik, Field, FieldArray, ErrorMessage } from 'formik';
import { Instruction, GapFillQuestion } from './components/components';
//import bootstrap CSS 
//rather lucily the webpack installed by wp-scripts includes a process for scss
//and by default builds it to index.css minified. 
import './custom.scss';

/**
 * 
 *  
 * https://formik.org/docs/examples/field-arrays
 * rendering problem: 
 * https://wordpress.org/support/topic/gutenberg-block-constantly-re-renders-on-state-change-in-inner-component/
 */

/**
 * react docs: https://reactjs.org/docs/hooks-state.html state v hooks 
 * https://reactjs.org/docs/hooks-intro.html
 * https://tsh.io/blog/react-component-lifecycle-methods-vs-hooks/
 * https://dev.to/martinkr/create-a-wordpress-s-gutenberg-block-with-all-react-lifecycle-methods-in-5-minutes-213p
 * https://www.youtube.com/watch?v=6x7GXs6Foaw
 * 
 * 
 * TODO https://blog.logrocket.com/a-guide-to-usestate-in-react-ecb9952e406c/#usinganobjectasastatevariablewithusestatehook
 *  check how I am doing this
 */


/*
WIP

save data entered into the body of the post into
the meta field
+ remove the meta field displayed in the editor

https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ - creating the block
with a text control 



TODO
fix sec issues in wp-scripts - run audit and maybe update version? try 16.0.0 not "^14.1.1"

*/  

const initialValues = {}; 
initialValues.set = false;
const defaultLang = 'en';
const additionalLangs = ['ru'];
const supportedLangs = [defaultLang, ...additionalLangs];
console.log("set to false");
//{`instructions.${idx}.text`} 



 
function useForceUpdate() {
    const [value, setValue] = useState(0); // integer state
    return () => setValue(value => value + 1); // update the state to force render
}



const FormWrapper = ({processForm, metaData, postType}) =>
{

    
    console.log("formwrapper called with metaData", metaData);
    const metaFieldValue = metaData[ '_activity_gap_fill_meta' ]; 
    
    function setInitialValues() {
            
            
            //The hook useEntityProp can be used by the blocks to get or change meta values.
            //in the register or to the backend? 
            //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ 
            
            
            //TODO get this from backend or make it configureable
            //also make en default
    
            //is this a mistake? newValue is the event?
            //this does the rest call to save the data?
            
            
            //this is case of new activity?
            if (metaFieldValue == "")
            {
                console.log("init form with no prior meta data");
                initialValues.type = postType;
                //initialValues.ageGroup = 0;
                //initialValues.level = 0;
                initialValues.legacyName = '';
                initialValues.title = '';
                initialValues.questions = [];
                initialValues.instructions = [];
                for (const lang of supportedLangs)
                {
                    initialValues.instructions.push({lang: lang, text: ''});
                }
              
                
            }
            else //set initialValues for form based on XML string loaded from postmeta
            {
                console.log("init form with prior meta data", metaFieldValue);
                let parser = new DOMParser();
                let xmlDoc = parser.parseFromString(metaFieldValue, "text/xml");
                let rootNode = xmlDoc.getElementsByTagName("activity")[0];
                initialValues.type = rootNode.getAttribute("type");
                //initialValues.ageGroup = rootNode.getAttribute("ageGroup");
                //initialValues.level = rootNode.getAttribute("level");

                let legacyNameNodes = xmlDoc.getElementsByTagName("legacyName");
                
                if ((legacyNameNodes.length > 0) && (legacyNameNodes[0].childNodes.length > 0) )
                {
                    initialValues.legacyName = legacyNameNodes[0].childNodes[0].nodeValue; 
                }
                else
                {
                    initialValues.legacyName = '';  
                }

                let titleNodes = xmlDoc.getElementsByTagName("title");
                if ((titleNodes.length > 0))
                {
                    initialValues.title = titleNodes[0].childNodes[0].nodeValue;     
                }
                else
                {
                    initialValues.title = '';
                }

                let questionNodes = xmlDoc.getElementsByTagName("questions");
                
                
                if (questionNodes.length > 0)
                {
                    let theQuestionsNode = questionNodes[0];
                    initialValues.questions = [];
                    for (let el of theQuestionsNode.childNodes) { 
                        let questionText = el.textContent;
                        let answerText = el.getAttribute("answer");
                        let question = {"question": questionText, "answer": answerText};
                        initialValues.questions.push(question); 
                    }
                }
                else
                {
                    initialValues.questions = [];        
                }

                let instructionsNodes = xmlDoc.getElementsByTagName("instructions");
                let instructionsHolder = new Array();
                if (instructionsNodes != null)
                {
                    let instructionNodes = instructionsNodes[0].childNodes;
                    instructionNodes.forEach((el) => {
                        const lang = el.getAttribute("lang");
                        instructionsHolder[lang] = el.textContent;     
                    });
                }
                initialValues.instructions = new Array();
                
                for (const lang of supportedLangs)
                {
                    if (lang in instructionsHolder)
                    {   
                        initialValues.instructions.push({lang: lang, text: instructionsHolder[lang]});    
                    }
                    else
                    {
                        initialValues.instructions.push({lang: lang, text: '' });   
                    }
                }

                let s = new XMLSerializer();
                let newXmlStr = s.serializeToString(xmlDoc);
                initialValues.rawXML = newXmlStr;
                

            }
            console.log("setting initialvalues" , initialValues);
            initialValues.set = true;
        }
        if (!initialValues.set)
        {
            setInitialValues();
        }
        
        {console.log("values--", initialValues)};
    return <div>
                
    <Formik
        initialValues={initialValues}

        validate={values => {
            console.log("validating");
            let errors = {};
           
            /*
            if (values.ageGroup == 0) {
                errors.ageGroup = "Required"; 
            }
            if (values.level == 0) {
                errors.level = "Required"; 
            }
            */
            if ((values.title == "") || (values.title.length <= 10 )) {
                errors.title = "Required and must be > 10 chars"; 
            }
            //TODO could valdiate for correct format ___ and |
           
            //https://formik.org/docs/guides/arrays
            //todo how to do my array of questions https://formik.org/docs/api/fieldarray

            values.instructions.forEach((item, idx) =>
            {
                if (item.lang == defaultLang)
                {
                    if (item.text == '')
                    {
                        errors.instructions = new Array();
                        errors.instructions[idx] = {"lang": '', "text": "Required"};
                    }
                }
            })
            
            values.questions.forEach((item, idx) =>
            {
                let errorObj = {"question": '', "answer": ''};

                if ((values.questions[idx].question == '') 
                    || (!values.questions[idx].question.includes("___")))
                {
                    if (errors.questions == undefined)
                    {
                        errors.questions = new Array();
                    }
                    errors.questions[idx] = errorObj;
                    errors.questions[idx].question = "Required and must contain ___";
                }
 
                //for formik to pass validation there must be no
                //questions field on the errors object at all. 
                //so only put it on if there is at least one error
                if ((values.questions[idx].answer == '') 
                    || (!values.questions[idx].answer.includes("|")))
                {
                    if (errors.questions == undefined)
                    {
                        errors.questions = new Array();
                    }
                    if (errors.questions[idx] == undefined)
                    {
                        errors.questions[idx] = errorObj;    
                    }
                    errors.questions[idx].answer = "Required and must contain |";
                }
            })
            
            console.log("errors", errors);
            
            return errors;
        }}
        
        onSubmit={(values) => {
            //FormIk validation happens here?
            console.log("called in onSubmit", values);
            processForm(values);
        }}
            
    >
        {({
            values,
            errors,
            touched,
            handleChange,
            handleBlur,
            handleSubmit,
            isSubmitting,
     
        }) => (
         
            
        <Form onSubmit={handleSubmit} name="activty" id="activity" className="">
            {/* 
            <Form.Group as={Row} >

                <Form.Label column sm={2}>Age Group</Form.Label>
                <Col sm={4}>
                    <Form.Control id="ageGroup" name="ageGroup" as="select"
                        onChange={handleChange}
                        onBlur={handleBlur}
                        value={values.ageGroup}
                        isInvalid={!!errors.ageGroup && !!touched.ageGroup}
                        >
                        <option value="0">Select..</option>
                        <option value="1">Kids</option>
                        <option value="2">Teens</option>
                        <option value="3">Adults</option>
                    </Form.Control>

                    {errors.ageGroup && touched.ageGroup ? 
                        <div className="invalid-feedback">
                        {errors.ageGroup}
                        </div> : null
                    }
                </Col>
                <Form.Label column sm={2}>Level</Form.Label>
                <Col sm={4}>
                    <Form.Control id="level" name="level" as="select"
                        onChange={handleChange}
                        onBlur={handleBlur}
                        value={values.level}
                        isInvalid={!!errors.level && !!touched.level}
                        >
                        <option value="0">Select..</option>
                        <option value="1">Beginner</option>
                        <option value="2">Intermediate</option>
                        <option value="3">Upper-Intermediate</option>
                    </Form.Control>

                    {errors.level && touched.level ? 
                        <div className="invalid-feedback">
                        {errors.level}
                        </div> : null
                    }
                </Col>
                </Form.Group> */}

            <Form.Group as={Row}>
                <Form.Label column md={2}>Title</Form.Label>
                <Col md={10}>
                    <Form.Control  name="title" id="title"
                        onChange={handleChange}
                        onBlur={handleBlur}
                        value={values.title}
                        isInvalid={!!errors.title && !!touched.title}
                     
                    ></Form.Control>

                    {errors.title && touched.title ? 
                        <div className="invalid-feedback">
                        {errors.title}
                        </div> : null
                    }
                </Col>
            </Form.Group>

            <Form.Group as={Row}>
                <Form.Label column sm={2}>Legacy Name (optional)</Form.Label>
                <Col md={10}>
                    <Form.Control md={10} name="legacyName" id="legacyName"
                        onChange={handleChange}
                        onBlur={handleBlur}
                        value={values.legacyName}
                        isInvalid={!!errors.legacyName && !!touched.legacyName}
                    ></Form.Control>
                    {errors.legacyName && touched.legacyName ? 
                        <div className="invalid-feedback">
                        {errors.legacyName}
                        </div> : null
                    }
                </Col>
            </Form.Group>

            {/*
            <Form.Group as={Row}>
            
                <Form.Label column md={2}>Instructions</Form.Label>

                <Col md={10}>
                    <Form.Control id="langSelector" name="langSelector" as="select"
                        onChange={setInstructionsLangHandler}
                        onBlur={handleBlur}
                        value={currentLang}
                        >
                        {
                            supportedLangs.map((lang) => {
                                return (<option value={lang}>{lang}</option>)
                            })
                        }

                    </Form.Control>
                </Col>
                    </Form.Group> */}

            <Form.Group as={Row}> 
                    <Col>
                        <h3>Instructions</h3>
                    </Col>
            </Form.Group>        

            <div>     
                {values.instructions.length > 0 && values.instructions.map((instruction, idx) =>           
                            <Instruction instruction={instruction} idx={idx} />
                )}     
            </div>

            <Row>
                <Col>
                    <h3>Questions</h3>
                    <p>To create a gap use ___ (3 underscores). Words in brackets separated by a comma.
                        Answers in second box separated by |</p>
                </Col>
            </Row>

            <FieldArray name="questions" validateOnChange={false}>
            {({ insert, remove, push }) => (
                <div>     
                    {values.questions.length > 0 && values.questions.map( (item, idx) =>            
                        <GapFillQuestion idx={idx} 
                            insert={insert}
                            remove={remove}
                            values={values} 
                            errors={errors} touched={touched} 
                            handleChange={handleChange} handleBlur={handleBlur} 
                            >
                        </GapFillQuestion>)
                    }
                    <div className="text-right margin-top-10">
                        <button
                            className="secondary btn btn-primary"
                            type="button"
                            onClick={() => push({ question: '', answer: '' })}>
                        +
                        </button>
                    </div>
                </div>
            )}
            </FieldArray>
               
            <Form.Group as={Row}>
                <Col md={12}>
                        {/*<Button onClick={() => addQuestion() }>+</Button>*/}
                </Col>
            </Form.Group>

            <Form.Group as={Row}>
                <Col md={12}>
                  
                    <Form.Label>Activity XML</Form.Label>
                    <Form.Control as="textarea" id="rawXML" name="rawXML" rows={6}
                        readOnly value={ values.rawXML }></Form.Control>
                  
                </Col>
            </Form.Group>
           

            
            <Form.Group as={Row}>
                <Col sm={{ span: 10, offset: 0 }}>
                    <button id="activityButton" role="link" type="submit" >
                        Update
                    </button>
                </Col>
            </Form.Group> 

            
        </Form>
        )}
    </Formik>
   

</div>
    
}



 
/* https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/writing-your-first-block-type/ */
//https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/ 
//https://deliciousbrains.com/custom-gutenberg-block/
registerBlockType( 'activities/activity-gap-fill', {
    apiVersion: 2,
    title: 'Activity Gap Fill',
    icon: 'universal-access-alt',
    category: 'design', /* custom https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#managing-block-categories */ 
    //we are bypassing attributes as we are saving just one block of xml to post meta not html string
    attributes: {
		exampleText: {
			type: 'string',
            source: 'text',
			default: ''
		}
	
	},

    //https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/introducing-attributes-and-editable-fields/
    //how am i going to get the meta data?
    //https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/ see Meta (deprecated)
    //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-1-intro/ 

   

    edit: ( { setAttributes, attributes } ) => {
        console.log("edit run values");
        const postType = useSelect(
            ( select ) => select( 'core/editor' ).getCurrentPostType(),
            []
        );
        
        //const forceUpdate = useForceUpdate();
     
        //this is run whenever a form field value is changed
        //edit = a functional component 
        //and useState goes here https://reactjs.org/docs/hooks-state.html
     
        const blockProps = useBlockProps();//? gets props passed to this 'edit' component?
        //TODO - does this make an ajax call or just get it from the data store
        const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' ); 

        //validate form, if ok build XML (second validation step - test for valid
        //valid XML - call setMeta to update the meta field with the XML
        function processForm(values)
        {
            //?? how to manage form data: Formik
            //attributes ?
            console.log("processForm", values);
            
            //idea is values should contain current form values
            //we now process them into XML and save that to 
            //the post meta field _activity_gap_fill_meta
            

            //create the XML object.
            let parser = new DOMParser();
            let xml = '<?xml version="1.0" encoding="UTF-8"?><activity></activity>';
            let xmlDoc = parser.parseFromString(xml,"text/xml");

            let rootNode = xmlDoc.getElementsByTagName("activity")[0];
            rootNode.setAttribute("type", postType);
            //rootNode.setAttribute("ageGroup", values.ageGroup);
            //rootNode.setAttribute("level", values.level);

            let legacyNameNode = xmlDoc.createElement("legacyName");
            let legacyNameValueNode = xmlDoc.createTextNode(values.legacyName);
            legacyNameNode.appendChild(legacyNameValueNode);
            xmlDoc.getElementsByTagName("activity")[0].appendChild(legacyNameNode);

            let titleNode = xmlDoc.createElement("title");
            let titleValueNode = xmlDoc.createTextNode(values.title);
            titleNode.appendChild(titleValueNode);
            xmlDoc.getElementsByTagName("activity")[0].appendChild(titleNode);

            let instructionsNode = xmlDoc.createElement("instructions");
            values.instructions.forEach(function(item, i)
            {
                let iNode = xmlDoc.createElement("instruction");
                iNode.setAttribute("lang", item.lang);
                let iValueNode = xmlDoc.createTextNode(item.text);
                iNode.appendChild(iValueNode);
                instructionsNode.appendChild(iNode);
            });
            xmlDoc.getElementsByTagName("activity")[0].appendChild(instructionsNode);

            let questionsNode = xmlDoc.createElement("questions");
            values.questions.forEach(function(item, i)
            {
                let qNode = xmlDoc.createElement("q"+i);
                qNode.setAttribute("questionNumber", (i + 1));
                qNode.setAttribute("answer", item.answer);
                let qValueNode = xmlDoc.createTextNode(item.question);
                qNode.appendChild(qValueNode);
                questionsNode.appendChild(qNode);
            });
            xmlDoc.getElementsByTagName("activity")[0].appendChild(questionsNode);

            let s = new XMLSerializer();
            let newXmlStr = s.serializeToString(xmlDoc);
            values.rawXML = newXmlStr;
        

            //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ 
            //this seems to cause a re-render of the component. does it?
            //but does not save anything to the backend - that takes saving the whole post
            //via the button on the page?
            //console.log("setMeta");
            setMeta( { ...meta, _activity_gap_fill_meta: newXmlStr } );
            
        }
  
 
        return (
            <div { ...blockProps }>
                <FormWrapper postType={postType} metaData={meta} processForm={processForm}></FormWrapper>
            </div>
        );
    },
 
    // No information saved to the block
    // Data is saved to post meta via the hook
    //normally this would build the html using the set attributes? and return an html
    //string to save - which is what is displayed on the f/e
    save() {
        return null;
    },
} );